<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSurveyPageRequest extends FormRequest
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
            'description' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Updated localized title map for the page.',
                'example' => [
                    'uz' => 'Baholash va takliflar',
                    'ru' => 'Оценка и предложения',
                    'en' => 'Rating & Feedback',
                ],
            ],
            'description' => [
                'description' => 'Updated localized page instructions.',
                'example' => [
                    'uz' => 'Yakuniy bahoni bering.',
                    'ru' => 'Поставьте итоговую оценку.',
                    'en' => 'Provide your final rating.',
                ],
            ],
        ];
    }
}
