<?php

namespace App\Modules\Participation\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['locale' => ['sometimes', 'nullable', 'string', Rule::in(PlatformLocale::values())]];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return ['locale' => ['description' => 'Survey language; defaults to survey default_locale for new participation.', 'example' => 'uz']];
    }
}
