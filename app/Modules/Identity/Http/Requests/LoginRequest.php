<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Registered user email address.',
                'example' => 'owner@dialog.uz',
            ],
            'password' => [
                'description' => 'User account password.',
                'example' => 'Secret123!',
            ],
            'device_name' => [
                'description' => 'Optional access-token device name. Defaults to web.',
                'example' => 'Android',
            ],
        ];
    }
}
