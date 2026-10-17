<?php

namespace App\Modules\Survey\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyRequest extends FormRequest
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
            'default_locale' => ['sometimes', 'string', Rule::in(PlatformLocale::values())],
            'locales' => ['sometimes', 'array', 'min:1'],
            'locales.*' => ['string', Rule::in(PlatformLocale::values())],
            'title' => ['sometimes', 'array'],
            'description' => ['nullable', 'array'],
            'welcome_message' => ['nullable', 'array'],
            'thank_you_message' => ['nullable', 'array'],
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
                'description' => 'The default survey language. Allowed values: uz, ru, en.',
                'example' => 'uz',
            ],
            'locales' => [
                'description' => 'List of survey languages. Allowed values: uz, ru, en.',
                'example' => ['uz', 'ru', 'en'],
            ],
            'title' => [
                'description' => 'Localized survey title map keyed by language code.',
                'example' => [
                    'uz' => 'Mijozlar qoniqishi so‘rovi (Yangilangan)',
                    'ru' => 'Опрос удовлетворенности клиентов (Обновленный)',
                    'en' => 'Customer Satisfaction Survey (Updated)',
                ],
            ],
            'description' => [
                'description' => 'Optional localized survey subtitle/description map.',
                'example' => [
                    'uz' => 'Xizmatlar sifatini oshirish bo‘yicha fikrlar',
                    'ru' => 'Мнения для улучшения качества сервиса',
                    'en' => 'Feedback to improve service quality',
                ],
            ],
            'welcome_message' => [
                'description' => 'Optional greeting displayed to respondents.',
                'example' => [
                    'uz' => 'Xush kelibsiz!',
                    'ru' => 'Добро пожаловать!',
                    'en' => 'Welcome!',
                ],
            ],
            'thank_you_message' => [
                'description' => 'Optional message displayed after completion.',
                'example' => [
                    'uz' => 'Rahmat!',
                    'ru' => 'Спасибо!',
                    'en' => 'Thank you!',
                ],
            ],
            'internal_note' => [
                'description' => 'Internal note for researchers.',
                'example' => 'Reviewed with stakeholders.',
            ],
        ];
    }
}
