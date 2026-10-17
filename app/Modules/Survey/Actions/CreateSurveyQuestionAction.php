<?php

namespace App\Modules\Survey\Actions;

use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Validators\QuestionSettingsValidator;

class CreateSurveyQuestionAction
{
    /**
     * @param  array{
     *     type: QuestionType|string,
     *     title: array<string, string>,
     *     description?: array<string, string>|null,
     *     is_required?: bool,
     *     settings?: array<string, mixed>
     * }  $data
     */
    public function execute(SurveyPage $page, array $data): SurveyQuestion
    {
        $page->survey->assertEditable();

        $type = $data['type'] instanceof QuestionType ? $data['type'] : QuestionType::from($data['type']);
        $settings = QuestionSettingsValidator::validate($type, $data['settings'] ?? []);

        $maxPosition = (int) SurveyQuestion::where('page_id', $page->id)->max('position');

        return SurveyQuestion::create([
            'page_id' => $page->id,
            'type' => $type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_required' => $data['is_required'] ?? false,
            'position' => $maxPosition + 1,
            'settings' => $settings,
        ]);
    }
}
