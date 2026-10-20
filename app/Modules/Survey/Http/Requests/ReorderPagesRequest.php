<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPagesRequest extends FormRequest
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
            'pages' => ['required', 'array', 'list', 'min:1'],
            'pages.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'pages' => [
                'description' => 'Ordered list of page IDs representing the new sequential order (1-indexed).',
                'example' => [2, 1, 3],
            ],
        ];
    }
}
