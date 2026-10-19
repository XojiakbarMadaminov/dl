<?php

namespace App\Modules\Participation\Services;

use App\Exceptions\ApiException;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;

class SurveyFlow
{
    public function __construct(private SurveyLogicEvaluator $logic) {}

    /**
     * Resolves the localized page payload and flow progression for respondent / preview.
     *
     * @return array{
     *     page: array<string, mixed>,
     *     progress: array{current_step: int, total_steps: int, percent: int, is_first_page: bool, is_last_page: bool},
     *     next: array{type: string, id?: int}
     * }
     */
    public function getStep(Survey $survey, SurveyPage $currentPage, string $locale, array $answers = []): array
    {
        if (! in_array($locale, $survey->locales, true)) {
            throw new ApiException(
                message: "Locale '{$locale}' is not supported by this survey.",
                errorCode: 'UNSUPPORTED_SURVEY_LOCALE',
                statusCode: 422,
            );
        }

        $allPages = $survey->pages()->orderBy('position')->orderBy('id')->get();
        $totalSteps = $allPages->count();

        $currentIndex = $allPages->search(fn (SurveyPage $p) => $p->id === $currentPage->id);
        if ($currentIndex === false) {
            throw new ApiException(
                message: 'Page does not belong to survey flow.',
                errorCode: 'INVALID_PAGE_FLOW',
                statusCode: 404,
            );
        }

        $currentStep = $currentIndex + 1;
        $isFirstPage = $currentStep === 1;
        $percent = $totalSteps > 0 ? (int) round(($currentStep / $totalSteps) * 100) : 100;

        $answers = $this->path($survey, $answers)['answers'];
        $nextDestination = $this->next($survey, $currentPage, $answers);

        $currentPage->loadMissing(['questions.options']);

        $localizedQuestions = [];
        foreach ($currentPage->questions as $question) {
            if (! $this->isVisible($survey, $question->id, $answers)) {
                continue;
            }
            $localizedOptions = [];
            foreach ($question->options as $option) {
                $localizedOptions[] = [
                    'id' => $option->id,
                    'position' => $option->position,
                    'label' => $option->label[$locale] ?? ($option->label[$survey->default_locale] ?? ''),
                ];
            }

            $settings = $question->settings;
            foreach (['min_label', 'max_label'] as $label) {
                if (isset($settings[$label]) && is_array($settings[$label])) {
                    $settings[$label] = $settings[$label][$locale] ?? null;
                }
            }

            $localizedQuestions[] = [
                'id' => $question->id,
                'type' => $question->type->value,
                'title' => $question->title[$locale] ?? ($question->title[$survey->default_locale] ?? ''),
                'description' => $question->description[$locale] ?? null,
                'is_required' => $question->is_required,
                'position' => $question->position,
                'settings' => $settings,
                'options' => $localizedOptions,
            ];
        }

        return [
            'page' => [
                'id' => $currentPage->id,
                'position' => $currentPage->position,
                'title' => $currentPage->title[$locale] ?? null,
                'description' => $currentPage->description[$locale] ?? null,
                'questions' => $localizedQuestions,
            ],
            'progress' => [
                'current_step' => $currentStep,
                'total_steps' => $totalSteps,
                'percent' => $percent,
                'is_first_page' => $isFirstPage,
                'is_last_page' => $nextDestination['type'] === 'completed',
            ],
            'next' => $nextDestination,
        ];
    }

    /**
     * Resolves the initial first page step.
     *
     * @return array<string, mixed>
     */
    public function getInitialStep(Survey $survey, string $locale): array
    {
        $firstPage = $survey->pages()->orderBy('position')->orderBy('id')->first();

        if (! $firstPage) {
            throw new ApiException(
                message: 'Survey has no pages.',
                errorCode: 'SURVEY_EMPTY',
                statusCode: 422,
            );
        }

        return $this->getStep($survey, $firstPage, $locale);
    }

    /** @param array<int|string, mixed> $answers */
    public function isVisible(Survey $survey, int $questionId, array $answers): bool
    {
        $survey->loadMissing('logicRules');
        $rule = $survey->logicRules->first(fn ($rule) => $rule->action === 'show_question' && $rule->target_question_id === $questionId);

        return $rule === null || $this->logic->matches($rule, $answers);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array{type: string, id?: int}
     */
    public function next(Survey $survey, SurveyPage $page, array $answers): array
    {
        $survey->loadMissing(['pages', 'logicRules']);
        foreach ($survey->logicRules as $rule) {
            if ($rule->action !== 'show_question' && $rule->source_page_id === $page->id && $this->logic->matches($rule, $answers)) {
                return $rule->action === 'complete' ? ['type' => 'completed'] : ['type' => 'page', 'id' => (int) $rule->target_page_id];
            }
        }
        $pages = $survey->pages->sortBy(fn ($candidate) => [$candidate->position, $candidate->id])->values();
        $index = $pages->search(fn ($candidate) => $candidate->id === $page->id);
        $nextPage = $index === false ? null : $pages->get($index + 1);

        return $nextPage ? ['type' => 'page', 'id' => $nextPage->id] : ['type' => 'completed'];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array{page_ids: array<int, int>, question_ids: array<int, int>, answers: array<int|string, mixed>}
     */
    public function path(Survey $survey, array $answers): array
    {
        $survey->loadMissing(['pages.questions.options', 'logicRules']);
        $pages = $survey->pages->sortBy(fn ($candidate) => [$candidate->position, $candidate->id])->values();
        $page = $pages->first();
        $pageIds = [];
        $questionIds = [];
        $applicableAnswers = [];

        while ($page !== null) {
            if (in_array($page->id, $pageIds, true)) {
                throw new ApiException('Survey logic contains a loop.', 'INVALID_SURVEY_LOGIC', 422);
            }
            $pageIds[] = $page->id;
            $visible = $page->questions->filter(fn ($question) => $this->isVisible($survey, $question->id, $applicableAnswers));
            foreach ($visible as $question) {
                $questionIds[] = $question->id;
                if (array_key_exists($question->id, $answers)) {
                    $value = $answers[$question->id];
                    $applicableAnswers[$question->id] = match ($question->type) {
                        QuestionType::SINGLE_CHOICE => (is_int($value) || is_string($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $value,
                        QuestionType::RATING => is_numeric($value) && is_finite((float) $value) && (float) $value === floor((float) $value) ? (int) $value : $value,
                        QuestionType::MULTIPLE_CHOICE => is_array($value) ? array_map(fn ($selected) => (is_int($selected) || is_string($selected)) && filter_var($selected, FILTER_VALIDATE_INT) !== false ? (int) $selected : $selected, $value) : $value,
                        default => $value,
                    };
                }
            }
            $destination = $this->next($survey, $page, $applicableAnswers);
            if ($destination['type'] === 'completed') {
                break;
            }
            $nextPage = $pages->firstWhere('id', $destination['id']);
            if ($nextPage === null || [$nextPage->position, $nextPage->id] <= [$page->position, $page->id]) {
                throw new ApiException('Survey logic has an invalid destination.', 'INVALID_SURVEY_LOGIC', 422);
            }
            $page = $nextPage;
        }

        return ['page_ids' => $pageIds, 'question_ids' => $questionIds, 'answers' => $applicableAnswers];
    }

    /** @return array<int, mixed> */
    public function answers(Participation $participation): array
    {
        return $participation->answers()->with(['question', 'options'])->get()->mapWithKeys(fn ($answer) => [
            $answer->question_id => match ($answer->question->type) {
                QuestionType::SINGLE_CHOICE => $answer->options->first()?->id,
                QuestionType::MULTIPLE_CHOICE => $answer->options->pluck('id')->all(),
                QuestionType::RATING => (int) $answer->number_value,
                QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT => $answer->text_value,
            },
        ])->all();
    }
}
