<?php

namespace App\Modules\Survey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string'],
            'answers' => ['present', 'array', 'max:1000'],
            'previous_answers' => ['sometimes', 'array', 'max:1000'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'locale' => ['description' => 'Survey locale; defaults to the survey default.', 'example' => 'uz'],
            'answers' => ['description' => 'Current-page direct scalar/array answers keyed by question ID.', 'example' => ['303' => 5]],
            'previous_answers' => ['description' => 'Earlier applicable preview page answers, never real persisted responses.', 'example' => ['101' => 502]],
        ];
    }
}
