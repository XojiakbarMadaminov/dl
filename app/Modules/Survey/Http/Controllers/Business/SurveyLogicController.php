<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Actions\ReplaceSurveyLogicAction;
use App\Modules\Survey\Http\Requests\ReplaceSurveyLogicRequest;
use App\Modules\Survey\Http\Resources\SurveyLogicRuleResource;
use App\Modules\Survey\Models\Survey;
use Illuminate\Http\JsonResponse;

/**
 * @group Business
 *
 * @subgroup Survey Logic
 */
class SurveyLogicController extends Controller
{
    /**
     * Read Survey Logic
     *
     * @authenticated
     *
     * @response 200 {"rules":[]}
     */
    public function index(Organization $organization, Survey $survey): JsonResponse
    {
        $this->authorize('view', $organization);
        $this->assertOrganization($organization, $survey);

        return response()->json(['rules' => SurveyLogicRuleResource::collection($survey->logicRules()->get())]);
    }

    /**
     * Replace Survey Logic
     *
     * Atomically replaces all rules. Send rules: [] to remove all logic. See API-SURVEY-LOGIC.md for the contract.
     *
     * @authenticated
     *
     * @response 200 {"rules":[{"id":1,"action":"show_question","source_page_id":null,"target_page_id":null,"target_question_id":202,"priority":0,"match":"all","conditions":[{"question_id":101,"operator":"equals","value":501}]}]}
     * @response 422 {"error":{"code":"INVALID_SURVEY_LOGIC","message":"Invalid survey logic.","fields":{"rules.0":["Navigation may only target a later page in this survey."]}}}
     */
    public function update(ReplaceSurveyLogicRequest $request, Organization $organization, Survey $survey, ReplaceSurveyLogicAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->assertOrganization($organization, $survey);
        $action->execute($survey, $request->validated('rules'), $request->user(), $request->ip());

        return response()->json(['rules' => SurveyLogicRuleResource::collection($survey->logicRules()->get())]);
    }

    private function assertOrganization(Organization $organization, Survey $survey): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException('Survey does not belong to this organization.', 'NOT_FOUND', 404);
        }
    }
}
