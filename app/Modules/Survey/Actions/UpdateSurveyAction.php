<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Identity\Enums\PlatformLocale;
use App\Modules\Survey\Models\Survey;

class UpdateSurveyAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Survey $survey, array $data): Survey
    {
        $survey->assertEditable();

        $locales = isset($data['locales'])
            ? array_values(array_unique((array) $data['locales']))
            : $survey->locales;

        $allowedLocales = PlatformLocale::values();
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

        $defaultLocale = $data['default_locale'] ?? $survey->default_locale;
        if (! in_array($defaultLocale, $locales, true)) {
            throw new ApiException(
                message: 'Default locale must be one of the selected locales.',
                errorCode: 'INVALID_DEFAULT_LOCALE',
                statusCode: 422,
                fields: ['default_locale' => ['Default locale must be included in locales list.']],
            );
        }

        $title = $data['title'] ?? $survey->title;
        $titleErrors = [];
        foreach ($locales as $locale) {
            if (! isset($title[$locale]) || trim((string) $title[$locale]) === '') {
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

        $survey->update([
            'default_locale' => $defaultLocale,
            'locales' => $locales,
            'title' => $title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $survey->description,
            'welcome_message' => array_key_exists('welcome_message', $data) ? $data['welcome_message'] : $survey->welcome_message,
            'thank_you_message' => array_key_exists('thank_you_message', $data) ? $data['thank_you_message'] : $survey->thank_you_message,
            'internal_note' => array_key_exists('internal_note', $data) ? $data['internal_note'] : $survey->internal_note,
        ]);

        return $survey;
    }
}
