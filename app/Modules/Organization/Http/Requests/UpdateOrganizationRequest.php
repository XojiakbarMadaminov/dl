<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('update', $this->route('organization'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('organizations', 'inn')->ignore($this->route('organization'))],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Updated display name for the organization.',
                'example' => 'Dialog Analytics Worldwide',
            ],
            'inn' => [
                'description' => 'Optional replacement INN. Must be a non-empty string unique to this organization (maximum 255 characters).',
                'example' => '123456789',
            ],
        ];
    }
}
