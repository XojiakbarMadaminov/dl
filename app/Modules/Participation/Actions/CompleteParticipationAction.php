<?php

namespace App\Modules\Participation\Actions;

use App\Exceptions\ApiException;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Participation\Validators\AnswerValidator;
use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Support\Facades\DB;

class CompleteParticipationAction
{
    public function __construct(protected AnswerValidator $answerValidator, private SurveyFlow $flow) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Participation $participation): array
    {
        return DB::transaction(function () use ($participation) {
            $participation = Participation::query()->lockForUpdate()->findOrFail($participation->id);

            return $this->complete($participation);
        });
    }

    /** @return array<string, mixed> */
    private function complete(Participation $participation): array
    {
        if ($participation->status->isCompleted()) {
            return [
                'completed' => true,
                'status' => 'completed',
                'thank_you_message' => $participation->survey->thank_you_message[$participation->locale] ?? null,
            ];
        }

        $participation->assertCanAcceptAnswers();
        $participation->load(['survey.pages.questions.options', 'answers.options']);
        $answers = $participation->answers->keyBy('question_id');
        $path = $this->flow->path($participation->survey, $this->flow->answers($participation));

        foreach ($participation->survey->pages as $page) {
            if (! in_array($page->id, $path['page_ids'], true)) {
                continue;
            }
            $pageAnswers = [];
            foreach ($page->questions as $question) {
                $answer = $answers->get($question->id);
                if ($answer === null) {
                    continue;
                }

                $pageAnswers[$question->id] = match ($question->type) {
                    QuestionType::SINGLE_CHOICE => $answer->options->first()?->id,
                    QuestionType::MULTIPLE_CHOICE => $answer->options->pluck('id')->all(),
                    QuestionType::RATING => $answer->number_value,
                    QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT => $answer->text_value,
                };
            }

            $this->answerValidator->validatePageAnswers($page, $pageAnswers, applicableQuestionIds: $path['question_ids']);
        }

        if ($participation->current_page_id !== end($path['page_ids'])) {
            throw new ApiException('Reach the end of the applicable survey path first.', 'INVALID_PAGE_FLOW', 422);
        }
        $participation->answers()->whereNotIn('question_id', $path['question_ids'])->delete();

        $participation->update([
            'status' => ParticipationStatus::COMPLETED,
            'current_page_id' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);

        return [
            'completed' => true,
            'status' => 'completed',
            'thank_you_message' => $participation->survey->thank_you_message[$participation->locale] ?? null,
        ];
    }
}
