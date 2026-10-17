<?php

namespace App\Modules\Survey\Actions;

use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Validators\QuestionSettingsValidator;

class UpdateSurveyQuestionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SurveyQuestion $question, array $data): SurveyQuestion
    {
        $question->page->survey->assertEditable();

        $type = isset($data['type'])
            ? ($data['type'] instanceof QuestionType ? $data['type'] : QuestionType::from($data['type']))
            : $question->type;

        $settings = array_key_exists('settings', $data)
            ? QuestionSettingsValidator::validate($type, (array) $data['settings'])
            : $question->settings;

        $question->update([
            'type' => $type,
            'title' => $data['title'] ?? $question->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $question->description,
            'is_required' => array_key_exists('is_required', $data) ? (bool) $data['is_required'] : $question->is_required,
            'settings' => $settings,
        ]);

        return $question;
    }
}
