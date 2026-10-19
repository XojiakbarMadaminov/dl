<?php

namespace App\Modules\Survey\Actions;

use App\Models\User;
use App\Modules\Platform\Audit\Services\AuditLogger;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Validators\SurveyLogicValidator;
use Illuminate\Support\Facades\DB;

class ReplaceSurveyLogicAction
{
    public function __construct(private SurveyLogicValidator $validator, private AuditLogger $auditLogger) {}

    /** @param array<int, array<string, mixed>> $rules */
    public function execute(Survey $survey, array $rules, User $user, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($survey, $rules, $user, $ipAddress) {
            $survey = Survey::query()->lockForUpdate()->findOrFail($survey->id);
            $survey->assertEditable();
            $this->validator->validate($survey, $rules);
            $survey->logicRules()->delete();
            $survey->logicRules()->createMany($rules);
            $this->auditLogger->log('survey.logic_replaced', $survey, $user, $survey->organization_id, ['rule_count' => count($rules)], $ipAddress);
        });
    }
}
