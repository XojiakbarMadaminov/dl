<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Actions\CreateSurveyQuestionAction;
use App\Modules\Survey\Actions\DuplicateQuestionAction;
use App\Modules\Survey\Actions\ReorderQuestionsAction;
use App\Modules\Survey\Actions\UpdateSurveyQuestionAction;
use App\Modules\Survey\Http\Requests\CreateQuestionRequest;
use App\Modules\Survey\Http\Requests\ReorderQuestionsRequest;
use App\Modules\Survey\Http\Requests\UpdateQuestionRequest;
use App\Modules\Survey\Http\Resources\SurveyQuestionResource;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Business
 *
 * @subgroup Survey Builder (Questions)
 *
 * Manage survey questions, types, localized labels, validation settings, and sequencing.
 */
class SurveyQuestionController extends Controller
{
    /**
     * Add Question to Page
     *
     * Create a new question within a survey page. Supported types: single_choice, multiple_choice, rating, short_text, long_text.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam page int required The ID of the page. Example: 1
     *
     * @response 201 {
     *   "question": {
     *     "id": 1,
     *     "page_id": 1,
     *     "type": "single_choice",
     *     "title": {
     *       "uz": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
     *       "ru": "Как часто вы пользуетесь нашим сервисом?",
     *       "en": "How often do you use our service?"
     *     },
     *     "description": null,
     *     "is_required": true,
     *     "position": 1,
     *     "settings": null,
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     */
    public function store(CreateQuestionRequest $request, Organization $organization, SurveyPage $page, CreateSurveyQuestionAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validatePageInOrg($page, $organization);

        $question = $action->execute($page, $request->validated());

        return response()->json([
            'question' => new SurveyQuestionResource($question),
        ], 201);
    }

    /**
     * Update Question
     *
     * Update question properties, localized titles, and type-specific settings. Survey must be editable.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam question int required The ID of the question. Example: 1
     *
     * @response 200 {
     *   "question": {
     *     "id": 1,
     *     "page_id": 1,
     *     "type": "single_choice",
     *     "title": {
     *       "uz": "Xizmatimizdan qanchalik tez-tez foydalanasiz? (Yangilangan)"
     *     },
     *     "is_required": true,
     *     "position": 1
     *   }
     * }
     */
    public function update(UpdateQuestionRequest $request, Organization $organization, SurveyQuestion $question, UpdateSurveyQuestionAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateQuestionInOrg($question, $organization);

        $question = $action->execute($question, $request->validated());

        return response()->json([
            'question' => new SurveyQuestionResource($question),
        ]);
    }

    /**
     * Delete Question
     *
     * Delete a question and its options from the draft survey.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam question int required The ID of the question. Example: 1
     *
     * @response 200 {
     *   "message": "Question deleted successfully."
     * }
     */
    public function destroy(Request $request, Organization $organization, SurveyQuestion $question): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateQuestionInOrg($question, $organization);
        $question->page->survey->assertEditable();

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully.',
        ]);
    }

    /**
     * Duplicate Question
     *
     * Deep-copy a question including its settings and localized options directly below the original question.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam question int required The ID of the question to duplicate. Example: 1
     *
     * @response 201 {
     *   "question": {
     *     "id": 2,
     *     "page_id": 1,
     *     "type": "single_choice",
     *     "title": {
     *       "uz": "Xizmatimizdan qanchalik tez-tez foydalanasiz? (Copy)",
     *       "ru": "Как часто вы пользуетесь нашим сервисом? (Copy)",
     *       "en": "How often do you use our service? (Copy)"
     *     },
     *     "position": 2
     *   }
     * }
     */
    public function duplicate(Request $request, Organization $organization, SurveyQuestion $question, DuplicateQuestionAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateQuestionInOrg($question, $organization);

        $duplicated = $action->execute($question);

        return response()->json([
            'question' => new SurveyQuestionResource($duplicated),
        ], 201);
    }

    /**
     * Reorder Questions
     *
     * Update sequential positions of questions on a given page.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam page int required The ID of the page. Example: 1
     *
     * @response 200 {
     *   "message": "Questions reordered successfully."
     * }
     */
    public function reorder(ReorderQuestionsRequest $request, Organization $organization, SurveyPage $page, ReorderQuestionsAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validatePageInOrg($page, $organization);

        $action->execute($page, $request->validated('questions'));

        return response()->json([
            'message' => 'Questions reordered successfully.',
        ]);
    }

    protected function validatePageInOrg(SurveyPage $page, Organization $organization): void
    {
        if ($page->survey->organization_id !== $organization->id) {
            throw new ApiException('Page not found in this organization.', 'NOT_FOUND', 404);
        }
    }

    protected function validateQuestionInOrg(SurveyQuestion $question, Organization $organization): void
    {
        if ($question->page->survey->organization_id !== $organization->id) {
            throw new ApiException('Question not found in this organization.', 'NOT_FOUND', 404);
        }
    }
}
