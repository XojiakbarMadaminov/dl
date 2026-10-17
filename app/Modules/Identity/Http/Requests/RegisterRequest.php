<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
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
            'locale' => ['nullable', 'string', Rule::in(PlatformLocale::values())],
            'organization_name' => ['nullable', 'string', 'min:2', 'max:255'],
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
            'locale' => [
                'description' => 'Preferred interface language (uz, ru, en). Default is uz.',
                'example' => 'uz',
            ],
            'organization_name' => [
                'description' => 'Optional organization name. When provided, automatically creates an organization with the registrant as OWNER.',
                'example' => 'Dialog Analytics Group',
            ],
        ];
    }
}
