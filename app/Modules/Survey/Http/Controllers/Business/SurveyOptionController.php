<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Actions\CreateOptionAction;
use App\Modules\Survey\Actions\ReorderOptionsAction;
use App\Modules\Survey\Http\Requests\CreateOptionRequest;
use App\Modules\Survey\Http\Requests\ReorderOptionsRequest;
use App\Modules\Survey\Http\Requests\UpdateOptionRequest;
use App\Modules\Survey\Http\Resources\SurveyOptionResource;
use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Business
 *
 * @subgroup Survey Builder (Options)
 *
 * Manage choice options for single_choice and multiple_choice questions.
 */
class SurveyOptionController extends Controller
{
    /**
     * Add Option to Question
     *
     * Add a choice option with localized labels to a choice question.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam question int required The ID of the question. Example: 1
     *
     * @response 201 {
     *   "option": {
     *     "id": 1,
     *     "question_id": 1,
     *     "position": 1,
     *     "label": {
     *       "uz": "Har kuni",
     *       "ru": "Каждый день",
     *       "en": "Daily"
     *     },
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     */
    public function store(CreateOptionRequest $request, Organization $organization, SurveyQuestion $question, CreateOptionAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateQuestionInOrg($question, $organization);

        $option = $action->execute($question, $request->validated());

        return response()->json([
            'option' => new SurveyOptionResource($option),
        ], 201);
    }

    /**
     * Update Option
     *
     * Update localized labels of an existing choice option.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam option int required The ID of the option. Example: 1
     *
     * @response 200 {
     *   "option": {
     *     "id": 1,
     *     "question_id": 1,
     *     "position": 1,
     *     "label": {
     *       "uz": "Har kuni (Yangilangan)"
     *     }
     *   }
     * }
     */
    public function update(UpdateOptionRequest $request, Organization $organization, SurveyQuestionOption $option): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateOptionInOrg($option, $organization);
        $option->question->page->survey->assertEditable();

        $option->update($request->validated());

        return response()->json([
            'option' => new SurveyOptionResource($option),
        ]);
    }

    /**
     * Delete Option
     *
     * Delete a choice option from a question.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam option int required The ID of the option. Example: 1
     *
     * @response 200 {
     *   "message": "Option deleted successfully."
     * }
     */
    public function destroy(Request $request, Organization $organization, SurveyQuestionOption $option): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateOptionInOrg($option, $organization);
        $option->question->page->survey->assertEditable();

        $option->delete();

        return response()->json([
            'message' => 'Option deleted successfully.',
        ]);
    }

    /**
     * Reorder Options
     *
     * Reorder choice options for a question.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam question int required The ID of the question. Example: 1
     *
     * @response 200 {
     *   "message": "Options reordered successfully."
     * }
     */
    public function reorder(ReorderOptionsRequest $request, Organization $organization, SurveyQuestion $question, ReorderOptionsAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateQuestionInOrg($question, $organization);

        $action->execute($question, $request->validated('options'));

        return response()->json([
            'message' => 'Options reordered successfully.',
        ]);
    }

    protected function validateQuestionInOrg(SurveyQuestion $question, Organization $organization): void
    {
        if ($question->page->survey->organization_id !== $organization->id) {
            throw new ApiException('Question not found in this organization.', 'NOT_FOUND', 404);
        }
    }

    protected function validateOptionInOrg(SurveyQuestionOption $option, Organization $organization): void
    {
        if ($option->question->page->survey->organization_id !== $organization->id) {
            throw new ApiException('Option not found in this organization.', 'NOT_FOUND', 404);
        }
    }
}
