<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Moderation\Actions\PublishSurveyAction;
use App\Modules\Moderation\Actions\SubmitSurveyForReviewAction;
use App\Modules\Moderation\Actions\TransitionSurveyStatusAction;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Http\Resources\SurveyResource;
use App\Modules\Survey\Models\Survey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Business
 *
 * @subgroup Survey Lifecycle
 *
 * Manage survey status transitions: submit for review, publish, pause, resume, close, and archive.
 */
class BusinessSurveyLifecycleController extends Controller
{
    /**
     * Submit Survey for Review
     *
     * Submit a completed draft survey to the platform moderation queue. Validates translation completeness and question structure.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "pending_review",
     *     "title": {"uz": "Mijozlar qoniqishi so‘rovi"}
     *   },
     *   "message": "Survey submitted for review successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "Survey must have at least one page with questions before submitting.",
     *     "fields": {}
     *   }
     * }
     */
    public function submitReview(
        Request $request,
        Organization $organization,
        Survey $survey,
        SubmitSurveyForReviewAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->execute(
            survey: $survey,
            user: $user,
            ipAddress: $request->ip(),
        );

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey submitted for review successfully.',
        ]);
    }

    /**
     * Publish Survey
     *
     * Activate an APPROVED survey so that respondents can participate. Once published, survey structure becomes immutable.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "active",
     *     "published_at": "2026-10-17T12:00:00.000000Z"
     *   },
     *   "message": "Survey published and activated successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "INVALID_STATUS_TRANSITION",
     *     "message": "Survey must be in APPROVED status to publish.",
     *     "fields": {}
     *   }
     * }
     */
    public function publish(
        Request $request,
        Organization $organization,
        Survey $survey,
        PublishSurveyAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->execute(
            survey: $survey,
            user: $user,
            ipAddress: $request->ip(),
        );

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey published and activated successfully.',
        ]);
    }

    /**
     * Pause Survey
     *
     * Temporarily pause an ACTIVE survey. Existing in-progress respondents can finish, but no new participations can be started.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "paused"
     *   },
     *   "message": "Survey paused successfully."
     * }
     */
    public function pause(
        Request $request,
        Organization $organization,
        Survey $survey,
        TransitionSurveyStatusAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->pause($survey, $user, $request->ip());

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey paused successfully.',
        ]);
    }

    /**
     * Resume Survey
     *
     * Resume a PAUSED survey back to ACTIVE status.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "active"
     *   },
     *   "message": "Survey resumed successfully."
     * }
     */
    public function resume(
        Request $request,
        Organization $organization,
        Survey $survey,
        TransitionSurveyStatusAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->resume($survey, $user, $request->ip());

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey resumed successfully.',
        ]);
    }

    /**
     * Close Survey
     *
     * Permanently close an ACTIVE or PAUSED survey to all responses.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "closed",
     *     "closed_at": "2026-10-17T12:00:00.000000Z"
     *   },
     *   "message": "Survey closed successfully."
     * }
     */
    public function close(
        Request $request,
        Organization $organization,
        Survey $survey,
        TransitionSurveyStatusAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->close($survey, $user, $request->ip());

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey closed successfully.',
        ]);
    }

    /**
     * Archive Survey
     *
     * Archive a CLOSED survey. Archived surveys are kept read-only for reporting and historic reference.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "archived",
     *     "archived_at": "2026-10-17T12:00:00.000000Z"
     *   },
     *   "message": "Survey archived successfully."
     * }
     */
    public function archive(
        Request $request,
        Organization $organization,
        Survey $survey,
        TransitionSurveyStatusAction $action,
    ): JsonResponse {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->archive($survey, $user, $request->ip());

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey archived successfully.',
        ]);
    }

    protected function validateBelongsToOrg(Survey $survey, Organization $organization): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException('Survey does not belong to this organization.', 'NOT_FOUND', 404);
        }
    }
}
