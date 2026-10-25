<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', 'max:255', 'unique:organizations,inn'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The legal or display name of the business organization.',
                'example' => 'Dialog Analytics Group',
            ],
            'inn' => [
                'description' => 'Unique organization INN, sent as a string (maximum 255 characters).',
                'example' => '123456789',
            ],
        ];
    }
}
