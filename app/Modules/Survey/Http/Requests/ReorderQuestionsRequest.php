<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQuestionsRequest extends FormRequest
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
            'questions' => ['required', 'array', 'list', 'min:1'],
            'questions.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, array{description: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'questions' => [
                'description' => 'Ordered list of question IDs representing the new order on this page (1-indexed).',
                'example' => [3, 1, 2],
            ],
        ];
    }
}
