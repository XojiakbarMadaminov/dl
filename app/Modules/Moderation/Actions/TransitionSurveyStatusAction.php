<?php

namespace App\Modules\Moderation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Platform\Audit\Services\AuditLogger;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class TransitionSurveyStatusAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function pause(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        if ($survey->status !== SurveyStatus::ACTIVE) {
            throw new ApiException(
                message: "Cannot pause survey from status '{$survey->status->value}'.",
                errorCode: 'INVALID_STATUS_TRANSITION',
                statusCode: 422,
            );
        }

        return $this->applyTransition($survey, SurveyStatus::PAUSED, 'survey.paused', $user, $ipAddress);
    }

    public function resume(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        if ($survey->status !== SurveyStatus::PAUSED) {
            throw new ApiException(
                message: "Cannot resume survey from status '{$survey->status->value}'.",
                errorCode: 'INVALID_STATUS_TRANSITION',
                statusCode: 422,
            );
        }

        return $this->applyTransition($survey, SurveyStatus::ACTIVE, 'survey.resumed', $user, $ipAddress);
    }

    public function close(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        if (! in_array($survey->status, [SurveyStatus::ACTIVE, SurveyStatus::PAUSED], true)) {
            throw new ApiException(
                message: "Cannot close survey from status '{$survey->status->value}'.",
                errorCode: 'INVALID_STATUS_TRANSITION',
                statusCode: 422,
            );
        }

        return DB::transaction(function () use ($survey, $user, $ipAddress) {
            $survey->update([
                'status' => SurveyStatus::CLOSED,
                'closed_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'survey.closed',
                entity: $survey,
                user: $user,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }

    public function archive(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        if ($survey->status !== SurveyStatus::CLOSED) {
            throw new ApiException(
                message: "Cannot archive survey from status '{$survey->status->value}'.",
                errorCode: 'INVALID_STATUS_TRANSITION',
                statusCode: 422,
            );
        }

        return DB::transaction(function () use ($survey, $user, $ipAddress) {
            $survey->update([
                'status' => SurveyStatus::ARCHIVED,
                'archived_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'survey.archived',
                entity: $survey,
                user: $user,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }

    protected function applyTransition(
        Survey $survey,
        SurveyStatus $targetStatus,
        string $auditAction,
        User $user,
        ?string $ipAddress = null,
    ): Survey {
        return DB::transaction(function () use ($survey, $targetStatus, $auditAction, $user, $ipAddress) {
            $survey->update(['status' => $targetStatus]);

            $this->auditLogger->log(
                action: $auditAction,
                entity: $survey,
                user: $user,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }
}
