<?php

namespace App\Modules\Survey\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Survey\Actions\PreviewSurveyStepAction;
use App\Modules\Survey\Http\Requests\PreviewStepRequest;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Validators\SurveyLogicValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Business
 *
 * @subgroup Preview
 *
 * Stateless survey preview and test-validation endpoints. Allows business users to preview survey flow in any supported locale without polluting production statistics.
 */
class SurveyPreviewController extends Controller
{
    /**
     * Preview Survey
     *
     * Previews the survey flow and initial page in the requested locale. Makes zero database writes.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @queryParam locale string Survey locale to preview (must be one of survey locales: uz, ru, en). Defaults to survey default_locale. Example: uz
     *
     * @response 200 {
     *   "survey": {
     *     "id": 1,
     *     "status": "draft",
     *     "locale": "uz",
     *     "title": "Mijozlar qoniqishi so‘rovi",
     *     "description": "Xizmatlarimiz sifatini oshirish bo‘yicha fikringiz muhim",
     *     "welcome_message": "Assalomu alaykum!",
     *     "thank_you_message": "Tashakkur!"
     *   },
     *   "current_step": {
     *     "page": {
     *       "id": 1,
     *       "title": "Xizmatlardan foydalanish",
     *       "description": null,
     *       "position": 1,
     *       "questions": [
     *         {
     *           "id": 1,
     *           "type": "single_choice",
     *           "title": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
     *           "description": null,
     *           "is_required": true,
     *           "position": 1,
     *           "options": [
     *             {"id": 1, "label": "Har kuni", "position": 1}
     *           ]
     *         }
     *       ]
     *     },
     *     "progress": {"current_step": 1, "total_steps": 2, "percent": 50, "is_first_page": true, "is_last_page": false},
     *     "next": {
     *       "type": "page",
     *       "id": 2
     *     }
     *   }
     * }
     */
    public function show(Request $request, Organization $organization, Survey $survey, SurveyFlow $flow): JsonResponse
    {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $locale = (string) $request->query('locale', $survey->default_locale);
        if (! in_array($locale, $survey->locales, true)) {
            throw new ApiException(
                message: "Locale '{$locale}' is not supported by this survey.",
                errorCode: 'UNSUPPORTED_SURVEY_LOCALE',
                statusCode: 422,
            );
        }

        app(SurveyLogicValidator::class)->validate($survey, $survey->logicRules()->get()->toArray());
        $step = $flow->getInitialStep($survey, $locale);

        return response()->json([
            'survey' => [
                'id' => $survey->id,
                'status' => $survey->status->value,
                'locale' => $locale,
                'title' => $survey->title[$locale] ?? '',
                'description' => $survey->description[$locale] ?? null,
                'welcome_message' => $survey->welcome_message[$locale] ?? null,
                'thank_you_message' => $survey->thank_you_message[$locale] ?? null,
            ],
            'current_step' => $step,
        ]);
    }

    /**
     * Validate Step in Preview
     *
     * Test-validates answers for a specific page in preview mode without recording responses in the database.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     * @urlParam page int required The ID of the page being validated. Example: 1
     *
     * @bodyParam locale string The preview locale. Example: uz
     * @bodyParam answers object required Map of question IDs to scalar/array answers. Example: {"1": 1}
     * @bodyParam previous_answers object Answers from earlier applicable preview pages; used for conditional visibility and branching. Example: {}
     *
     * @response 200 {
     *   "valid": true,
     *   "current_step": {
     *     "page": {
     *       "id": 1,
     *       "title": "Xizmatlardan foydalanish",
     *       "position": 1
     *     },
     *     "progress": {"current_step": 1, "total_steps": 2, "percent": 50, "is_first_page": true, "is_last_page": false},
     *     "next": {
     *       "type": "page",
     *       "id": 2
     *     }
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "INVALID_ANSWER",
     *     "message": "One or more answers are invalid.",
     *     "fields": {
     *       "answers.1": ["This question is required."]
     *     }
     *   }
     * }
     */
    public function validateStep(
        PreviewStepRequest $request,
        Organization $organization,
        Survey $survey,
        SurveyPage $page,
        PreviewSurveyStepAction $action,
    ): JsonResponse {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        if ($page->survey_id !== $survey->id) {
            throw new ApiException('Page does not belong to survey.', 'NOT_FOUND', 404);
        }

        $locale = (string) $request->input('locale', $survey->default_locale);

        return response()->json($action->execute($survey, $page, $locale, $request->validated('answers'), $request->validated('previous_answers', [])));
    }

    protected function validateBelongsToOrg(Survey $survey, Organization $organization): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException('Survey does not belong to this organization.', 'NOT_FOUND', 404);
        }
    }
}
