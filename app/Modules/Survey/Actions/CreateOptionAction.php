<?php

namespace App\Modules\Survey\Actions;

use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;

class CreateOptionAction
{
    /**
     * @param  array{label: array<string, string>}  $data
     */
    public function execute(SurveyQuestion $question, array $data): SurveyQuestionOption
    {
        $question->page->survey->assertEditable();

        $maxPosition = (int) SurveyQuestionOption::where('question_id', $question->id)->max('position');

        return SurveyQuestionOption::create([
            'question_id' => $question->id,
            'label' => $data['label'],
            'position' => $maxPosition + 1,
        ]);
    }
}
