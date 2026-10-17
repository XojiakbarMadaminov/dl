<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Support\Facades\DB;

class CreateSurveyAction
{
    /**
     * @param  array{
     *     default_locale: string,
     *     locales: array<int, string>,
     *     title: array<string, string>,
     *     description?: array<string, string>|null,
     *     welcome_message?: array<string, string>|null,
     *     thank_you_message?: array<string, string>|null,
     *     internal_note?: string|null
     * }  $data
     */
    public function execute(Organization $organization, User $creator, array $data): Survey
    {
        $allowedLocales = PlatformLocale::values();
        $locales = array_values(array_unique($data['locales']));

        foreach ($locales as $locale) {
            if (! in_array($locale, $allowedLocales, true)) {
                throw new ApiException(
                    message: "Locale '{$locale}' is not supported.",
                    errorCode: 'UNSUPPORTED_LOCALE',
                    statusCode: 422,
                    fields: ['locales' => ["Locale '{$locale}' is not supported."]],
                );
            }
        }

        if (! in_array($data['default_locale'], $locales, true)) {
            throw new ApiException(
                message: 'Default locale must be one of the selected locales.',
                errorCode: 'INVALID_DEFAULT_LOCALE',
                statusCode: 422,
                fields: ['default_locale' => ['Default locale must be included in locales list.']],
            );
        }

        $titleErrors = [];
        foreach ($locales as $locale) {
            if (! isset($data['title'][$locale]) || trim((string) $data['title'][$locale]) === '') {
                $titleErrors["title.{$locale}"] = ["Title in '{$locale}' is required."];
            }
        }

        if (! empty($titleErrors)) {
            throw new ApiException(
                message: 'Survey title is required for all selected locales.',
                errorCode: 'VALIDATION_ERROR',
                statusCode: 422,
                fields: $titleErrors,
            );
        }

        return DB::transaction(function () use ($organization, $creator, $data, $locales) {
            $survey = Survey::create([
                'organization_id' => $organization->id,
                'created_by' => $creator->id,
                'status' => SurveyStatus::DRAFT,
                'default_locale' => $data['default_locale'],
                'locales' => $locales,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'welcome_message' => $data['welcome_message'] ?? null,
                'thank_you_message' => $data['thank_you_message'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
            ]);

            // Create initial default first page
            SurveyPage::create([
                'survey_id' => $survey->id,
                'position' => 1,
                'title' => null,
                'description' => null,
            ]);

            return $survey->load('pages');
        });
    }
}
