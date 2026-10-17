<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Actions\CreateSurveyAction;
use App\Modules\Survey\Actions\UpdateSurveyAction;
use App\Modules\Survey\Http\Requests\CreateSurveyRequest;
use App\Modules\Survey\Http\Requests\UpdateSurveyRequest;
use App\Modules\Survey\Http\Resources\SurveyResource;
use App\Modules\Survey\Models\Survey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Business
 *
 * @subgroup Surveys
 *
 * Manage organization surveys, metadata, localized titles, and lifecycles.
 */
class SurveyController extends Controller
{
    /**
     * List Surveys
     *
     * List all surveys belonging to the given organization. Returns paginated list of surveys.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     *
     * @queryParam page int Page number for pagination. Example: 1
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "organization_id": 1,
     *       "status": "draft",
     *       "default_locale": "uz",
     *       "locales": ["uz", "ru", "en"],
     *       "title": {
     *         "uz": "Mijozlar qoniqishi so‘rovi",
     *         "ru": "Опрос удовлетворенности клиентов",
     *         "en": "Customer Satisfaction Survey"
     *       },
     *       "description": null,
     *       "welcome_message": null,
     *       "thank_you_message": null,
     *       "internal_note": "Q4 Survey",
     *       "published_at": null,
     *       "closed_at": null,
     *       "archived_at": null,
     *       "created_at": "2026-10-17T12:00:00.000000Z"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost:8000/api/v1/business/organizations/1/surveys?page=1",
     *     "last": "http://localhost:8000/api/v1/business/organizations/1/surveys?page=1",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 1,
     *     "per_page": 20,
     *     "to": 1,
     *     "total": 1
     *   }
     * }
     */
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        $surveys = Survey::where('organization_id', $organization->id)
            ->latest()
            ->paginate(20);

        return SurveyResource::collection($surveys);
    }

    /**
     * Create Draft Survey
     *
     * Create a new draft survey within an organization. Selected locales must be a subset of [uz, ru, en], and default_locale must belong to selected locales.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     *
     * @response 201 {
     *   "survey": {
     *     "id": 1,
     *     "organization_id": 1,
     *     "status": "draft",
     *     "default_locale": "uz",
     *     "locales": ["uz", "ru", "en"],
     *     "title": {
     *       "uz": "Mijozlar qoniqishi so‘rovi",
     *       "ru": "Опрос удовлетворенности клиентов",
     *       "en": "Customer Satisfaction Survey"
     *     },
     *     "description": null,
     *     "welcome_message": null,
     *     "thank_you_message": null,
     *     "internal_note": "Q4 Survey",
     *     "published_at": null,
     *     "closed_at": null,
     *     "archived_at": null,
     *     "created_at": "2026-10-17T12:00:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "The title.uz field is required.",
     *     "fields": {
     *       "title.uz": ["Title is required for selected locale: uz"]
     *     }
     *   }
     * }
     */
    public function store(CreateSurveyRequest $request, Organization $organization, CreateSurveyAction $action): JsonResponse
    {
        $this->authorize('createSurvey', $organization);

        /** @var User $user */
        $user = $request->user();

        $survey = $action->execute(
            organization: $organization,
            creator: $user,
            data: $request->validated(),
        );

        return response()->json([
            'survey' => new SurveyResource($survey),
        ], 201);
    }

    /**
     * Get Survey Details
     *
     * Fetch complete survey hierarchy including pages, questions, and options.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "organization_id": 1,
     *     "status": "draft",
     *     "default_locale": "uz",
     *     "locales": ["uz", "ru", "en"],
     *     "title": {
     *       "uz": "Mijozlar qoniqishi so‘rovi",
     *       "ru": "Опрос удовлетворенности клиентов",
     *       "en": "Customer Satisfaction Survey"
     *     },
     *     "pages": [
     *       {
     *         "id": 1,
     *         "position": 1,
     *         "title": {"uz": "Xizmatlardan foydalanish", "ru": "Использование сервисов", "en": "Service Usage"},
     *         "questions": [
     *           {
     *             "id": 1,
     *             "type": "single_choice",
     *             "title": {"uz": "Savol matni", "ru": "Текст вопроса", "en": "Question text"},
     *             "is_required": true,
     *             "position": 1,
     *             "options": [
     *               {"id": 1, "label": {"uz": "Ha", "ru": "Да", "en": "Yes"}, "position": 1}
     *             ]
     *           }
     *         ]
     *       }
     *     ]
     *   }
     * }
     */
    public function show(Request $request, Organization $organization, Survey $survey): JsonResponse
    {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $survey->load(['pages.questions.options']);

        return response()->json([
            'survey' => new SurveyResource($survey),
        ]);
    }

    /**
     * Update Survey
     *
     * Update metadata, title, or locales of a draft survey. Surveys in active, closed, or archived status cannot be edited.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "organization_id": 1,
     *     "status": "draft",
     *     "default_locale": "uz",
     *     "locales": ["uz", "ru", "en"],
     *     "title": {"uz": "Mijozlar qoniqishi so‘rovi (Yangilangan)"}
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "SURVEY_NOT_EDITABLE",
     *     "message": "Survey cannot be modified in its current status: active.",
     *     "fields": {}
     *   }
     * }
     */
    public function update(UpdateSurveyRequest $request, Organization $organization, Survey $survey, UpdateSurveyAction $action): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $survey = $action->execute($survey, $request->validated());

        return response()->json([
            'survey' => new SurveyResource($survey),
        ]);
    }

    /**
     * Delete Survey
     *
     * Permanently delete a survey. Only draft surveys or surveys requesting changes can be deleted. Active surveys cannot be deleted.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "message": "Survey deleted successfully."
     * }
     * @response 422 {
     *   "error": {
     *     "code": "SURVEY_NOT_EDITABLE",
     *     "message": "Survey cannot be modified in its current status: active.",
     *     "fields": {}
     *   }
     * }
     */
    public function destroy(Request $request, Organization $organization, Survey $survey): JsonResponse
    {
        $this->authorize('editSurvey', $organization);
        $this->validateBelongsToOrg($survey, $organization);
        $survey->assertEditable();

        $survey->delete();

        return response()->json([
            'message' => 'Survey deleted successfully.',
        ]);
    }

    protected function validateBelongsToOrg(Survey $survey, Organization $organization): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException(
                message: 'Survey does not belong to this organization.',
                errorCode: 'NOT_FOUND',
                statusCode: 404,
            );
        }
    }
}
