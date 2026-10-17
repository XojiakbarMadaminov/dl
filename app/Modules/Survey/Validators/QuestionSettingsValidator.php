<?php

namespace App\Modules\Survey\Validators;

use App\Exceptions\ApiException;
use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Support\Facades\Validator;

class QuestionSettingsValidator
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function validate(QuestionType $type, array $settings): array
    {
        $rules = match ($type) {
            QuestionType::SINGLE_CHOICE => [
                'randomize_options' => ['sometimes', 'boolean'],
            ],
            QuestionType::MULTIPLE_CHOICE => [
                'min_selections' => ['sometimes', 'integer', 'min:1'],
                'max_selections' => ['sometimes', 'integer', 'gte:min_selections'],
                'randomize_options' => ['sometimes', 'boolean'],
            ],
            QuestionType::RATING => [
                'min' => ['sometimes', 'integer', 'min:0'],
                'max' => ['sometimes', 'integer', 'gt:min'],
                'min_label' => ['sometimes', 'array'],
                'max_label' => ['sometimes', 'array'],
            ],
            QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT => [
                'max_length' => ['sometimes', 'integer', 'min:1'],
            ],
        };

        $validator = Validator::make($settings, $rules);

        if ($validator->fails()) {
            throw new ApiException(
                message: 'Invalid question settings.',
                errorCode: 'INVALID_QUESTION_SETTINGS',
                statusCode: 422,
                fields: $validator->errors()->toArray(),
            );
        }

        return $validator->validated();
    }
}
