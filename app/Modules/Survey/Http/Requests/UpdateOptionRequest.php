<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOptionRequest extends FormRequest
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
            'label' => ['required', 'array'],
            'label.*' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'label' => [
                'description' => 'Updated localized option label map.',
                'example' => [
                    'uz' => 'Haftada bir necha marta',
                    'ru' => 'Несколько раз в неделю',
                    'en' => 'Several times a week',
                ],
            ],
        ];
    }
}
