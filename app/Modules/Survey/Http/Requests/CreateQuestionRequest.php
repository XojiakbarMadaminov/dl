<?php

namespace App\Modules\Survey\Http\Requests;

use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(QuestionType::values())],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'type' => [
                'description' => 'Question type enum. Supported values: single_choice, multiple_choice, rating, short_text, long_text.',
                'example' => 'single_choice',
            ],
            'title' => [
                'description' => 'Localized question title map keyed by survey language codes.',
                'example' => [
                    'uz' => 'Xizmatimizdan qanchalik tez-tez foydalanasiz?',
                    'ru' => 'Как часто вы пользуетесь нашим сервисом?',
                    'en' => 'How often do you use our service?',
                ],
            ],
            'description' => [
                'description' => 'Optional localized helper text or subtitle for the question.',
                'example' => [
                    'uz' => 'Faqat bitta variantni tanlang',
                    'ru' => 'Выберите один вариант',
                    'en' => 'Select one option only',
                ],
            ],
            'is_required' => [
                'description' => 'Whether the respondent must answer this question before progressing.',
                'example' => true,
            ],
            'settings' => [
                'description' => 'Type-specific configuration: for rating: {"min": 1, "max": 5}; for multiple_choice: {"min_choices": 1, "max_choices": 3}; for text: {"min_length": 5, "max_length": 500}.',
                'example' => ['min' => 1, 'max' => 5],
            ],
        ];
    }
}
