<?php

namespace App\Modules\Moderation\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Platform\Audit\Services\AuditLogger;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Validators\SurveyLogicValidator;
use Illuminate\Support\Facades\DB;

class PublishSurveyAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SurveyLogicValidator $logicValidator,
    ) {}

    public function execute(Survey $survey, User $user, ?string $ipAddress = null): Survey
    {
        return DB::transaction(function () use ($survey, $user, $ipAddress) {
            $survey = Survey::query()->lockForUpdate()->findOrFail($survey->id);
            if ($survey->status === SurveyStatus::ACTIVE) {
                return $survey;
            }

            if ($survey->status !== SurveyStatus::APPROVED) {
                throw new ApiException(
                    message: "Cannot publish survey with status '{$survey->status->value}'. Only approved surveys can be published.",
                    errorCode: 'INVALID_STATUS_TRANSITION',
                    statusCode: 422,
                );
            }

            $this->logicValidator->validate($survey, $survey->logicRules()->get()->toArray());

            $survey->update([
                'status' => SurveyStatus::ACTIVE,
                'published_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'survey.published',
                entity: $survey,
                user: $user,
                organizationId: $survey->organization_id,
                ipAddress: $ipAddress,
            );

            return $survey;
        });
    }
}
