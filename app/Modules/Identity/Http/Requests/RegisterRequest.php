<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['sometimes', 'required', 'string', 'same:password'],
            'locale' => ['nullable', 'string', Rule::in(PlatformLocale::values())],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Full name of the registrant.',
                'example' => 'Dilshod Karimov',
            ],
            'email' => [
                'description' => 'Unique email address for account login.',
                'example' => 'owner@dialog.uz',
            ],
            'password' => [
                'description' => 'Account password (minimum 8 characters).',
                'example' => 'Secret123!',
            ],
            'password_confirmation' => [
                'description' => 'Optional password confirmation; must match the password when supplied.',
                'example' => 'Secret123!',
            ],
            'locale' => [
                'description' => 'Preferred interface language (uz, ru, en). Default is uz.',
                'example' => 'uz',
            ],
        ];
    }
}
