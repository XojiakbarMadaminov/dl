<?php

namespace App\Modules\Participation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Models\Survey;

class StartOrResumeParticipationAction
{
    public function execute(Survey $survey, User $user, ?string $requestedLocale = null): Participation
    {
        if (! $survey->status->isActive()) {
            throw new ApiException(
                message: 'This survey is not currently accepting responses.',
                errorCode: 'SURVEY_NOT_ACTIVE',
                statusCode: 422,
            );
        }

        $locale = $requestedLocale ?? $survey->default_locale;
        if (! in_array($locale, $survey->locales, true)) {
            throw new ApiException(
                message: "Locale '{$locale}' is not supported by this survey.",
                errorCode: 'UNSUPPORTED_SURVEY_LOCALE',
                statusCode: 422,
            );
        }

        $existing = Participation::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->status->isCompleted()) {
                throw new ApiException(
                    message: 'You have already completed this survey.',
                    errorCode: 'SURVEY_ALREADY_COMPLETED',
                    statusCode: 422,
                );
            }

            $existing->update(['last_activity_at' => now()]);

            return $existing;
        }

        $firstPage = $survey->pages()->orderBy('position')->first();
        if (! $firstPage) {
            throw new ApiException(
                message: 'Survey has no content.',
                errorCode: 'SURVEY_EMPTY',
                statusCode: 422,
            );
        }

        return Participation::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'locale' => $locale,
            'status' => ParticipationStatus::IN_PROGRESS,
            'current_page_id' => $firstPage->id,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}
