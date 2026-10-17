<?php

namespace App\Modules\Survey\Actions;

use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Support\Facades\DB;

class DuplicateQuestionAction
{
    public function execute(SurveyQuestion $original): SurveyQuestion
    {
        $original->page->survey->assertEditable();

        return DB::transaction(function () use ($original) {
            $maxPosition = (int) SurveyQuestion::where('page_id', $original->page_id)->max('position');

            $duplicate = SurveyQuestion::create([
                'page_id' => $original->page_id,
                'type' => $original->type,
                'title' => $original->title,
                'description' => $original->description,
                'is_required' => $original->is_required,
                'position' => $maxPosition + 1,
                'settings' => $original->settings,
            ]);

            foreach ($original->options as $option) {
                SurveyQuestionOption::create([
                    'question_id' => $duplicate->id,
                    'label' => $option->label,
                    'position' => $option->position,
                ]);
            }

            return $duplicate->load('options');
        });
    }
}
