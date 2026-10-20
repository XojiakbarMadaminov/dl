<?php

namespace App\Modules\Participation\Actions;

use App\Exceptions\ApiException;
use App\Modules\Participation\Models\Answer;
use App\Modules\Participation\Models\Participation;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Participation\Validators\AnswerValidator;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Support\Facades\DB;

class SavePageAnswersAction
{
    public function __construct(
        protected AnswerValidator $answerValidator,
        protected SurveyFlow $surveyFlow,
    ) {}

    /**
     * @param  array<string|int, mixed>  $answers
     * @return array<string, mixed>
     */
    public function execute(Participation $participation, SurveyPage $page, array $answers): array
    {
        return DB::transaction(function () use ($participation, $page, $answers) {
            $participation = Participation::query()->lockForUpdate()->findOrFail($participation->id);
            $participation->assertCanAcceptAnswers();

            if ($page->survey_id !== $participation->survey_id) {
                throw new ApiException(
                    message: 'Page does not belong to this survey.',
                    errorCode: 'INVALID_PAGE_FLOW',
                    statusCode: 422,
                );
            }

            $persistedAnswers = $this->surveyFlow->answers($participation);
            $path = $this->surveyFlow->path($participation->survey, $persistedAnswers);
            $history = $participation->page_history ?: array_slice($path['page_ids'], 0, (int) array_search($participation->current_page_id, $path['page_ids'], true) + 1);
            if (! in_array($page->id, $path['page_ids'], true) || ($page->id !== $participation->current_page_id && ! in_array($page->id, $history, true))) {
                throw new ApiException('Page has not been reached in this participation.', 'INVALID_PAGE_FLOW', 422);
            }
            $this->answerValidator->validatePageAnswers($page, $answers, applicableQuestionIds: $path['question_ids']);
            $history = array_slice($history, 0, (int) array_search($page->id, $history, true) + 1);

            $questionIds = $page->questions()->pluck('id')->all();

            // Delete old answers and pivot options for these questions
            $existingAnswers = Answer::where('participation_id', $participation->id)
                ->whereIn('question_id', $questionIds)
                ->get();

            foreach ($existingAnswers as $oldAnswer) {
                $oldAnswer->options()->detach();
                $oldAnswer->delete();
            }

            // Persist page answers atomically
            foreach ($page->questions as $question) {
                if (! in_array($question->id, $path['question_ids'], true)) {
                    continue;
                }
                $qKey = (string) $question->id;
                $hasAnswer = array_key_exists($qKey, $answers) || array_key_exists($question->id, $answers);
                if (! $hasAnswer) {
                    continue;
                }

                $val = $answers[$qKey] ?? $answers[$question->id];
                if ($val === null || $val === '' || $val === []) {
                    continue;
                }

                $answer = Answer::create([
                    'participation_id' => $participation->id,
                    'question_id' => $question->id,
                    'text_value' => in_array($question->type, [QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT], true) ? (string) $val : null,
                    'number_value' => $question->type === QuestionType::RATING ? (float) $val : null,
                ]);

                if ($question->type === QuestionType::SINGLE_CHOICE) {
                    $answer->options()->attach((int) $val);
                } elseif ($question->type === QuestionType::MULTIPLE_CHOICE) {
                    $answer->options()->attach((array) $val);
                }
            }

            $participation->update(['last_activity_at' => now()]);

            // Calculate next destination via SurveyFlow
            $updatedPath = $this->surveyFlow->path($participation->survey, $this->surveyFlow->answers($participation));
            $participation->answers()->whereNotIn('question_id', $updatedPath['question_ids'])->delete();
            $participation->update(['current_page_id' => $page->id, 'page_history' => $history]);
            $step = $this->surveyFlow->getStep($participation->survey, $page, $participation->locale, $updatedPath['answers']);

            if ($step['next']['type'] === 'completed') {
                return [
                    'next' => $step['next'],
                    'can_complete' => true,
                    'current_step' => $step,
                ];
            }

            $nextPageId = $step['next']['id'];
            $history[] = $nextPageId;
            $participation->update(['current_page_id' => $nextPageId, 'page_history' => $history]);

            $nextPage = SurveyPage::findOrFail($nextPageId);
            $nextStep = $this->surveyFlow->getStep($participation->survey, $nextPage, $participation->locale, $updatedPath['answers']);

            return [
                'next' => $step['next'],
                'can_complete' => false,
                'next_step' => $nextStep,
            ];
        });
    }
}
