<?php

namespace App\Modules\Participation\Http\Requests;

use App\Modules\Participation\Models\Participation;
use Illuminate\Foundation\Http\FormRequest;

class SavePageAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $participation = $this->route('participation');

        return $participation instanceof Participation && $participation->user_id === $this->user()?->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['answers' => ['present', 'array']];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return ['answers' => [
            'description' => 'Map of question IDs to answers: integer option ID, distinct option IDs array, integer rating, or text string.',
            'example' => '{"1":1}',
        ]];
    }
}
