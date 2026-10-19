<?php

namespace App\Modules\Participation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class StartOrResumeParticipationAction
{
    public function __construct(protected SurveyFlow $surveyFlow) {}

    public function execute(Survey $survey, User $user, ?string $requestedLocale = null): Participation
    {
        return DB::transaction(function () use ($survey, $user, $requestedLocale) {
            $survey = Survey::query()->lockForUpdate()->findOrFail($survey->id);

            return $this->startOrResume($survey, $user, $requestedLocale);
        });
    }

    private function startOrResume(Survey $survey, User $user, ?string $requestedLocale): Participation
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
            if (! $existing->status->isCompleted()) {
                $existing->update(['last_activity_at' => now()]);
            }

            return $existing;
        }

        $initialStep = $this->surveyFlow->getInitialStep($survey, $locale);

        return Participation::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'locale' => $locale,
            'status' => ParticipationStatus::IN_PROGRESS,
            'current_page_id' => $initialStep['page']['id'],
            'page_history' => [$initialStep['page']['id']],
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}
