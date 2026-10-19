<?php

namespace App\Modules\Survey\Actions;

use App\Exceptions\ApiException;
use App\Modules\Moderation\Models\SurveyModeration;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class DeleteSurveyAction
{
    public function execute(Survey $survey): void
    {
        DB::transaction(function () use ($survey) {
            $survey = Survey::query()->lockForUpdate()->findOrFail($survey->id);
            $survey->assertEditable();

            if ($survey->status !== SurveyStatus::DRAFT
                || SurveyModeration::query()->where('survey_id', $survey->id)->exists()
                || Participation::query()->where('survey_id', $survey->id)->exists()) {
                throw new ApiException('Surveys with history cannot be deleted.', 'SURVEY_HAS_HISTORY', 422);
            }

            $survey->delete();
        });
    }
}
