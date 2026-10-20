<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderOptionsRequest extends FormRequest
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
            'options' => ['required', 'array', 'list', 'min:1'],
            'options.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'options' => [
                'description' => 'Ordered list of option IDs representing the new order under this question (1-indexed).',
                'example' => [2, 1],
            ],
        ];
    }
}
