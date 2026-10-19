<?php

namespace App\Modules\Survey\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSurveyRequest extends FormRequest
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
            'default_locale' => ['required', 'string', Rule::in(PlatformLocale::values())],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['required', 'string', Rule::in(PlatformLocale::values())],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'welcome_message' => ['nullable', 'array'],
            'welcome_message.*' => ['nullable', 'string'],
            'thank_you_message' => ['nullable', 'array'],
            'thank_you_message.*' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'default_locale' => [
                'description' => 'The default survey language. Must be one of the selected locales: uz, ru, en.',
                'example' => 'uz',
            ],
            'locales' => [
                'description' => 'List of languages supported by this survey. Allowed values: uz, ru, en.',
                'example' => ['uz', 'ru', 'en'],
            ],
            'title' => [
                'description' => 'Localized survey title map. Must contain non-empty strings for all selected survey locales.',
                'example' => [
                    'uz' => 'Mijozlar qoniqishi so‘rovi',
                    'ru' => 'Опрос удовлетворенности клиентов',
                    'en' => 'Customer Satisfaction Survey',
                ],
            ],
            'title.uz' => [
                'description' => 'Survey title in Uzbek (required if uz is selected).',
                'example' => 'Mijozlar qoniqishi so‘rovi',
            ],
            'title.ru' => [
                'description' => 'Survey title in Russian (required if ru is selected).',
                'example' => 'Опрос удовлетворенности клиентов',
            ],
            'title.en' => [
                'description' => 'Survey title in English (required if en is selected).',
                'example' => 'Customer Satisfaction Survey',
            ],
            'description' => [
                'description' => 'Optional localized survey subtitle/description map.',
                'example' => [
                    'uz' => 'Xizmatlar sifatini yaxshilash bo‘yicha fikringiz',
                    'ru' => 'Ваше мнение для улучшения качества сервиса',
                    'en' => 'Your feedback to improve service quality',
                ],
            ],
            'welcome_message' => [
                'description' => 'Optional greeting displayed to respondents prior to answering.',
                'example' => [
                    'uz' => 'Assalomu alaykum! So‘rovnomamizga xush kelibsiz.',
                    'ru' => 'Добро пожаловать в наш опрос!',
                    'en' => 'Welcome to our satisfaction survey!',
                ],
            ],
            'thank_you_message' => [
                'description' => 'Optional completion message displayed after submission.',
                'example' => [
                    'uz' => 'Ishtirokingiz uchun katta rahmat!',
                    'ru' => 'Спасибо за участие!',
                    'en' => 'Thank you for your valuable participation!',
                ],
            ],
            'internal_note' => [
                'description' => 'Internal note for researchers (not visible to respondents).',
                'example' => 'Q4 2026 Customer Satisfaction Track',
            ],
        ];
    }
}
