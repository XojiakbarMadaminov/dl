<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSurveyPageRequest extends FormRequest
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
            'title' => ['nullable', 'array'],
            'title.*' => ['nullable', 'string'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Optional localized title map for the page.',
                'example' => [
                    'uz' => 'Xizmatlardan foydalanish',
                    'ru' => 'Использование сервисов',
                    'en' => 'Service Usage',
                ],
            ],
            'description' => [
                'description' => 'Optional localized explanatory instructions for the page.',
                'example' => [
                    'uz' => 'Iltimos, barcha savollarga xolis javob bering.',
                    'ru' => 'Пожалуйста, ответьте на все вопросы.',
                    'en' => 'Please answer all questions objectively.',
                ],
            ],
        ];
    }
}
