<?php

namespace App\Modules\Survey\Actions;

use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;

class CreateSurveyPageAction
{
    /**
     * @param  array{title?: array<string, string>|null, description?: array<string, string>|null}  $data
     */
    public function execute(Survey $survey, array $data = []): SurveyPage
    {
        $survey->assertEditable();

        $maxPosition = (int) SurveyPage::where('survey_id', $survey->id)->max('position');

        return SurveyPage::create([
            'survey_id' => $survey->id,
            'position' => $maxPosition + 1,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }
}
