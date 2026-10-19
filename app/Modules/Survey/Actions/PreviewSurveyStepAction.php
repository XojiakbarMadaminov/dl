<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Participation\Validators\AnswerValidator;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Validators\SurveyLogicValidator;

class PreviewSurveyStepAction
{
    public function __construct(private SurveyFlow $flow, private AnswerValidator $validator, private SurveyLogicValidator $logicValidator) {}

    public function execute(Survey $survey, SurveyPage $page, string $locale, array $answers, array $previousAnswers): array
    {
        $this->logicValidator->validate($survey, $survey->logicRules()->get()->toArray());
        $path = $this->flow->path($survey, $previousAnswers);
        if (! in_array($page->id, $path['page_ids'], true)) {
            throw new ApiException('Page is not on the preview path.', 'INVALID_PAGE_FLOW', 422);
        }
        $earlierQuestionIds = [];
        foreach ($survey->pages as $earlierPage) {
            if ($earlierPage->id === $page->id) {
                break;
            }
            if (in_array($earlierPage->id, $path['page_ids'], true)) {
                $this->validator->validatePageAnswers($earlierPage, $previousAnswers, applicableQuestionIds: $path['question_ids']);
                $earlierQuestionIds = array_merge($earlierQuestionIds, $earlierPage->questions->pluck('id')->all());
            }
        }
        if (array_diff(array_keys($previousAnswers), $earlierQuestionIds) !== []) {
            throw new ApiException('Previous answers must belong to earlier applicable pages.', 'INVALID_PAGE_FLOW', 422);
        }
        $this->validator->validatePageAnswers($page, $answers, applicableQuestionIds: $path['question_ids']);
        $currentAnswers = array_intersect_key($answers, array_flip($page->questions->pluck('id')->all()));
        $combined = array_replace($previousAnswers, $currentAnswers);
        $path = $this->flow->path($survey, $combined);

        return ['valid' => true, 'current_step' => $this->flow->getStep($survey, $page, $locale, $path['answers'])];
    }
}
