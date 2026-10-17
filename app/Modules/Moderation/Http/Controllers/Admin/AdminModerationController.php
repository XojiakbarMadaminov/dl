<?php

namespace App\Modules\Moderation\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Moderation\Actions\ApproveSurveyAction;
use App\Modules\Moderation\Actions\RequestChangesSurveyAction;
use App\Modules\Survey\Enums\SurveyStatus;
use App\Modules\Survey\Http\Resources\SurveyResource;
use App\Modules\Survey\Models\Survey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Admin
 *
 * @subgroup Moderation
 *
 * Platform moderation queue for reviewing submitted business surveys, approving or requesting changes before publication.
 */
class AdminModerationController extends Controller
{
    /**
     * Moderation Queue
     *
     * List surveys pending review or in other moderation states. Restricted to platform administrators.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (default is pending_review). Allowed: pending_review, approved, changes_requested. Example: pending_review
     * @queryParam page int Page number. Example: 1
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "organization_id": 1,
     *       "status": "pending_review",
     *       "title": {"uz": "Mijozlar qoniqishi so‘rovi"},
     *       "created_at": "2026-10-17T12:00:00.000000Z"
     *     }
     *   ]
     * }
     * @response 403 {
     *   "error": {
     *     "code": "FORBIDDEN",
     *     "message": "Admin privileges required.",
     *     "fields": {}
     *   }
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureAdmin($request);

        $status = $request->query('status', SurveyStatus::PENDING_REVIEW->value);

        $surveys = Survey::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['organization', 'creator'])
            ->latest()
            ->paginate(20);

        return SurveyResource::collection($surveys);
    }

    /**
     * Review Survey Details
     *
     * Fetch complete survey hierarchy (pages, questions, options, translations, and organization info) for moderation review.
     *
     * @authenticated
     *
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "pending_review",
     *     "title": {
     *       "uz": "Mijozlar qoniqishi so‘rovi",
     *       "ru": "Опрос удовлетворенности клиентов",
     *       "en": "Customer Satisfaction Survey"
     *     },
     *     "pages": [
     *       {
     *         "id": 1,
     *         "title": {"uz": "Xizmatlardan foydalanish"},
     *         "questions": [
     *           {
     *             "id": 1,
     *             "type": "single_choice",
     *             "title": {"uz": "Xizmatimizdan qanchalik tez-tez foydalanasiz?"}
     *           }
     *         ]
     *       }
     *     ]
     *   }
     * }
     */
    public function show(Request $request, Survey $survey): JsonResponse
    {
        $this->ensureAdmin($request);

        $survey->load(['organization', 'creator', 'pages.questions.options']);

        return response()->json([
            'survey' => new SurveyResource($survey),
        ]);
    }

    /**
     * Approve Survey
     *
     * Approve a survey in PENDING_REVIEW status. Transitions status to APPROVED, enabling the business to publish.
     *
     * @authenticated
     *
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "approved"
     *   },
     *   "message": "Survey approved successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "INVALID_STATUS_TRANSITION",
     *     "message": "Survey cannot be approved in its current status: draft.",
     *     "fields": {}
     *   }
     * }
     */
    public function approve(Request $request, Survey $survey, ApproveSurveyAction $action): JsonResponse
    {
        $this->ensureAdmin($request);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->execute(
            survey: $survey,
            moderator: $user,
            ipAddress: $request->ip(),
        );

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Survey approved successfully.',
        ]);
    }

    /**
     * Request Changes
     *
     * Reject or request adjustments for a survey in PENDING_REVIEW status. Requires explanatory feedback comment.
     *
     * @authenticated
     *
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @bodyParam comment string required Explanatory comment outlining required fixes (min 3 characters). Example: Iltimos, ruscha tarjimalardagi orfografik xatolarni to'g'rilang.
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "changes_requested"
     *   },
     *   "message": "Changes requested successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "The comment field is required.",
     *     "fields": {
     *       "comment": ["The comment field is required."]
     *     }
     *   }
     * }
     */
    public function requestChanges(Request $request, Survey $survey, RequestChangesSurveyAction $action): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->execute(
            survey: $survey,
            moderator: $user,
            comment: (string) $request->input('comment'),
            ipAddress: $request->ip(),
        );

        return response()->json([
            'survey' => new SurveyResource($survey),
            'message' => 'Changes requested successfully.',
        ]);
    }

    protected function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            throw new ApiException(
                message: 'Admin privileges required.',
                errorCode: 'FORBIDDEN',
                statusCode: 403,
            );
        }
    }
}
