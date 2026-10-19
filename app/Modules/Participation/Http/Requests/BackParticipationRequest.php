<?php

namespace App\Modules\Participation\Http\Requests;

use App\Modules\Participation\Models\Participation;
use Illuminate\Foundation\Http\FormRequest;

class BackParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $participation = $this->route('participation');

        return $participation instanceof Participation && $participation->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [];
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
