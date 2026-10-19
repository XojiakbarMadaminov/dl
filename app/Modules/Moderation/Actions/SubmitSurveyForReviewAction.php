<?php

namespace App\Modules\Moderation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Moderation\Enums\ModerationDecision;
use App\Modules\Moderation\Models\SurveyModeration;
use App\Modules\Moderation\Validators\SurveyPublishValidator;
use App\Modules\Platform\Audit\Services\AuditLogger;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class SubmitSurveyForReviewAction
{
    public function __construct(
        protected SurveyPublishValidator $publishValidator,
        protected AuditLogger $auditLogger,
    ) {}

    public function execute(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        return DB::transaction(function () use ($survey, $user, $ipAddress) {
            $survey = Survey::query()->lockForUpdate()->findOrFail($survey->id);
            if ($survey->status === SurveyStatus::PENDING_REVIEW) {
                return $survey;
            }

            if (! in_array($survey->status, [SurveyStatus::DRAFT, SurveyStatus::CHANGES_REQUESTED], true)) {
                throw new ApiException(
                    message: "Cannot submit survey for review from status '{$survey->status->value}'.",
                    errorCode: 'INVALID_STATUS_TRANSITION',
                    statusCode: 422,
                );
            }

            $this->publishValidator->validate($survey);

            $survey->update([
                'status' => SurveyStatus::PENDING_REVIEW,
            ]);

            SurveyModeration::create([
                'survey_id' => $survey->id,
                'decision' => ModerationDecision::SUBMITTED,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'survey.submitted_for_review',
                entity: $survey,
                user: $user,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }
}
