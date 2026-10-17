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
        $participation->assertCanAcceptAnswers();

        if ($page->survey_id !== $participation->survey_id) {
            throw new ApiException(
                message: 'Page does not belong to this survey.',
                errorCode: 'INVALID_PAGE_FLOW',
                statusCode: 422,
            );
        }

        // Validate only applicable questions on this page
        $this->answerValidator->validatePageAnswers($page, $answers);

        return DB::transaction(function () use ($participation, $page, $answers) {
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
            $step = $this->surveyFlow->getStep($participation->survey, $page, $participation->locale);

            if ($step['next'] === 'complete') {
                return [
                    'next' => 'complete',
                    'can_complete' => true,
                    'current_step' => $step,
                ];
            }

            $nextPageId = (int) $step['next'];
            $participation->update(['current_page_id' => $nextPageId]);

            $nextPage = SurveyPage::findOrFail($nextPageId);
            $nextStep = $this->surveyFlow->getStep($participation->survey, $nextPage, $participation->locale);

            return [
                'next' => $nextPageId,
                'can_complete' => false,
                'next_step' => $nextStep,
            ];
        });
    }
}
