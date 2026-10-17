<?php

namespace App\Modules\Participation\Services;

use App\Exceptions\ApiException;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;

class SurveyFlow
{
    /**
     * Resolves the localized page payload and flow progression for respondent / preview.
     *
     * @return array{
     *     page: array<string, mixed>,
     *     progress: array{current_step: int, total_steps: int, percent: int, is_first_page: bool, is_last_page: bool},
     *     next: int|string
     * }
     */
    public function getStep(Survey $survey, SurveyPage $currentPage, string $locale): array
    {
        if (! in_array($locale, $survey->locales, true)) {
            throw new ApiException(
                message: "Locale '{$locale}' is not supported by this survey.",
                errorCode: 'UNSUPPORTED_SURVEY_LOCALE',
                statusCode: 422,
            );
        }

        $allPages = $survey->pages()->orderBy('position')->get();
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
        $isLastPage = $currentStep === $totalSteps;
        $percent = $totalSteps > 0 ? (int) round(($currentStep / $totalSteps) * 100) : 100;

        $nextPage = $isLastPage ? null : $allPages->get($currentIndex + 1);
        $nextDestination = $nextPage ? $nextPage->id : 'complete';

        $currentPage->loadMissing(['questions.options']);

        $localizedQuestions = [];
        foreach ($currentPage->questions as $question) {
            $localizedOptions = [];
            foreach ($question->options as $option) {
                $localizedOptions[] = [
                    'id' => $option->id,
                    'position' => $option->position,
                    'label' => $option->label[$locale] ?? ($option->label[$survey->default_locale] ?? ''),
                ];
            }

            $localizedQuestions[] = [
                'id' => $question->id,
                'type' => $question->type->value,
                'title' => $question->title[$locale] ?? ($question->title[$survey->default_locale] ?? ''),
                'description' => $question->description[$locale] ?? null,
                'is_required' => $question->is_required,
                'position' => $question->position,
                'settings' => $question->settings,
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
                'is_last_page' => $isLastPage,
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
        $firstPage = $survey->pages()->orderBy('position')->first();

        if (! $firstPage) {
            throw new ApiException(
                message: 'Survey has no pages.',
                errorCode: 'SURVEY_EMPTY',
                statusCode: 422,
            );
        }

        return $this->getStep($survey, $firstPage, $locale);
    }
}
