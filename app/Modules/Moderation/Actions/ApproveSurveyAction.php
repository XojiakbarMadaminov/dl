<?php

namespace App\Modules\Moderation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Moderation\Enums\ModerationDecision;
use App\Modules\Moderation\Models\SurveyModeration;
use App\Modules\Platform\Audit\Services\AuditLogger;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class ApproveSurveyAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function execute(Survey $survey, User $moderator, ?string $ipAddress = null): Survey
    {
        if ($survey->status !== SurveyStatus::PENDING_REVIEW) {
            throw new ApiException(
                message: "Cannot approve survey with status '{$survey->status->value}'.",
                errorCode: 'INVALID_STATUS_TRANSITION',
                statusCode: 422,
            );
        }

        return DB::transaction(function () use ($survey, $moderator, $ipAddress) {
            $survey->update([
                'status' => SurveyStatus::APPROVED,
            ]);

            SurveyModeration::create([
                'survey_id' => $survey->id,
                'moderator_id' => $moderator->id,
                'decision' => ModerationDecision::APPROVED,
                'decided_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'survey.approved',
                entity: $survey,
                user: $moderator,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }
}
