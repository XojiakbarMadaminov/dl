<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOptionRequest extends FormRequest
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
                'description' => 'Localized choice option label map keyed by language code.',
                'example' => [
                    'uz' => 'Har kuni',
                    'ru' => 'Каждый день',
                    'en' => 'Daily',
                ],
            ],
            'label.uz' => [
                'description' => 'Option text in Uzbek.',
                'example' => 'Har kuni',
            ],
            'label.ru' => [
                'description' => 'Option text in Russian.',
                'example' => 'Каждый день',
            ],
            'label.en' => [
                'description' => 'Option text in English.',
                'example' => 'Daily',
            ],
        ];
    }
}
