<?php

namespace App\Modules\Results\Http\Requests;

use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Participation\Enums\ParticipationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SurveyResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ParticipationStatus::class)],
            'participation_locale' => ['sometimes', 'string', Rule::in(PlatformLocale::values())],
            'locale' => ['sometimes', 'string', Rule::in(PlatformLocale::values())],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Filter by participation status; omit to include both statuses.', 'example' => 'completed'],
            'participation_locale' => ['description' => 'Filter by stored participation locale.', 'example' => 'ru'],
            'locale' => ['description' => 'Display language for question and option labels, not a participation filter.', 'example' => 'uz'],
        ];
    }
}
