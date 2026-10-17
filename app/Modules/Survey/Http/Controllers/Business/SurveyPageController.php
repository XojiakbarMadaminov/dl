<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Actions\CreateSurveyPageAction;
use App\Modules\Survey\Actions\ReorderPagesAction;
use App\Modules\Survey\Http\Requests\CreateSurveyPageRequest;
use App\Modules\Survey\Http\Requests\ReorderPagesRequest;
use App\Modules\Survey\Http\Requests\UpdateSurveyPageRequest;
use App\Modules\Survey\Http\Resources\SurveyPageResource;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Business
 *
 * @subgroup Survey Builder (Pages)
 *
 * Manage survey pages, localized page titles/descriptions, and page sequence.
 */
class SurveyPageController extends Controller
{
    /**
     * Add Page to Survey
     *
     * Appends a new page to the draft survey. Automatically assigns the next sequential position.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 201 {
     *   "page": {
     *     "id": 2,
     *     "survey_id": 1,
     *     "position": 2,
     *     "title": {"uz": "Baholash", "ru": "Оценка", "en": "Rating"},
     *     "description": null,
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     */
    public function store(CreateSurveyPageRequest $request, Organization $organization, Survey $survey, CreateSurveyPageAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateSurveyInOrg($survey, $organization);

        $page = $action->execute($survey, $request->validated());

        return response()->json([
            'page' => new SurveyPageResource($page),
        ], 201);
    }

    /**
     * Update Page
     *
     * Update page title or description. Survey must be editable (draft or changes requested).
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam page int required The ID of the page. Example: 2
     *
     * @response 200 {
     *   "page": {
     *     "id": 2,
     *     "survey_id": 1,
     *     "position": 2,
     *     "title": {"uz": "Baholash va takliflar"},
     *     "description": null
     *   }
     * }
     */
    public function update(UpdateSurveyPageRequest $request, Organization $organization, SurveyPage $page): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validatePageInOrg($page, $organization);
        $page->survey->assertEditable();

        $page->update($request->validated());

        return response()->json([
            'page' => new SurveyPageResource($page),
        ]);
    }

    /**
     * Delete Page
     *
     * Deletes a page and all its questions and options. A survey must retain at least one page.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam page int required The ID of the page. Example: 2
     *
     * @response 200 {
     *   "message": "Page deleted successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "CANNOT_DELETE_LAST_PAGE",
     *     "message": "Survey must contain at least one page.",
     *     "fields": {}
     *   }
     * }
     */
    public function destroy(Request $request, Organization $organization, SurveyPage $page): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validatePageInOrg($page, $organization);
        $page->survey->assertEditable();

        if ($page->survey->pages()->count() <= 1) {
            throw new ApiException(
                message: 'Survey must contain at least one page.',
                errorCode: 'CANNOT_DELETE_LAST_PAGE',
                statusCode: 422,
            );
        }

        $page->delete();

        return response()->json([
            'message' => 'Page deleted successfully.',
        ]);
    }

    /**
     * Reorder Pages
     *
     * Update sequential positions of pages for a survey. Expects an array containing all page IDs in desired sequence.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "message": "Pages reordered successfully."
     * }
     */
    public function reorder(ReorderPagesRequest $request, Organization $organization, Survey $survey, ReorderPagesAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateSurveyInOrg($survey, $organization);

        $action->execute($survey, $request->validated('pages'));

        return response()->json([
            'message' => 'Pages reordered successfully.',
        ]);
    }

    protected function validateSurveyInOrg(Survey $survey, Organization $organization): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException('Survey not found in this organization.', 'NOT_FOUND', 404);
        }
    }

    protected function validatePageInOrg(SurveyPage $page, Organization $organization): void
    {
        if ($page->survey->organization_id !== $organization->id) {
            throw new ApiException('Page not found in this organization.', 'NOT_FOUND', 404);
        }
    }
}
