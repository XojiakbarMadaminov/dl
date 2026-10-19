<?php

namespace App\Modules\Survey\Http\Requests;

use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::in(QuestionType::values())],
            'title' => ['sometimes', 'array'],
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
                'description' => 'Question type enum: single_choice, multiple_choice, rating, short_text, long_text.',
                'example' => 'single_choice',
            ],
            'title' => [
                'description' => 'Updated localized question title map.',
                'example' => [
                    'uz' => 'Xizmatimizdan qanchalik tez-tez foydalanasiz? (Yangilangan)',
                    'ru' => 'Как часто вы пользуетесь нашим сервисом? (Обновлено)',
                    'en' => 'How often do you use our service? (Updated)',
                ],
            ],
            'description' => [
                'description' => 'Updated localized description or instructions.',
                'example' => [
                    'uz' => 'Iltimos, aniq javob bering.',
                ],
            ],
            'is_required' => [
                'description' => 'Mandatory answer requirement toggle.',
                'example' => true,
            ],
            'settings' => [
                'description' => 'Type-specific configuration settings.',
                'example' => ['min' => 1, 'max' => 5],
            ],
        ];
    }
}
