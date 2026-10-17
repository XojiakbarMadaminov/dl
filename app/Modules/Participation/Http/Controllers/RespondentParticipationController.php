<?php

namespace App\Modules\Participation\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Participation\Actions\CompleteParticipationAction;
use App\Modules\Participation\Actions\SavePageAnswersAction;
use App\Modules\Participation\Actions\StartOrResumeParticipationAction;
use App\Modules\Participation\Models\Participation;
use App\Modules\Participation\Services\SurveyFlow;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Respondent
 *
 * Core respondent participation flow: starting or resuming surveys, saving page answers, and completing surveys.
 */
class RespondentParticipationController extends Controller
{
    /**
     * Start or Resume Participation
     *
     * Starts participation in an ACTIVE survey or resumes an in-progress session. Guarantees exactly one participation per user per survey (`UNIQUE(survey_id, user_id)`).
     *
     * @authenticated
     *
     * @urlParam survey int required The ID of the active survey. Example: 1
     *
     * @bodyParam locale string Preferred survey language (must be one of survey locales: uz, ru, en). Optional; defaults to user profile locale or survey default_locale. Example: uz
     *
     * @response 201 {
     *   "participation_id": 1,
     *   "status": "in_progress",
     *   "survey": {
     *     "id": 1,
     *     "title": "Mijozlar qoniqishi so‘rovi",
     *     "welcome_message": "Assalomu alaykum! So‘rovnomaga xush kelibsiz."
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
     *           "is_required": true,
     *           "position": 1,
     *           "options": [
     *             {"id": 1, "label": "Har kuni", "position": 1},
     *             {"id": 2, "label": "Haftada bir necha marta", "position": 2}
     *           ]
     *         }
     *       ]
     *     },
     *     "is_first_page": true,
     *     "is_last_page": false,
     *     "next": {
     *       "type": "page",
     *       "page_id": 2
     *     }
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "SURVEY_NOT_ACTIVE",
     *     "message": "Survey is not open for responses.",
     *     "fields": {}
     *   }
     * }
     */
    public function participate(
        Request $request,
        Survey $survey,
        StartOrResumeParticipationAction $action,
        SurveyFlow $flow,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $locale = $request->input('locale');
        $participation = $action->execute(
            survey: $survey,
            user: $user,
            requestedLocale: is_string($locale) ? $locale : null,
        );

        $currentPage = $participation->currentPage ?? $survey->pages()->orderBy('position')->firstOrFail();
        $step = $flow->getStep($survey, $currentPage, $participation->locale);

        return response()->json([
            'participation_id' => $participation->id,
            'status' => $participation->status->value,
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title[$participation->locale] ?? '',
                'welcome_message' => $survey->welcome_message[$participation->locale] ?? null,
            ],
            'current_step' => $step,
        ], 201);
    }

    /**
     * Get Current Participation Step
     *
     * Load the current survey page and answered state for an in-progress session.
     *
     * @authenticated
     *
     * @urlParam participation int required The ID of the participation session. Example: 1
     *
     * @response 200 {
     *   "participation_id": 1,
     *   "status": "in_progress",
     *   "current_step": {
     *     "page": {
     *       "id": 1,
     *       "title": "Xizmatlardan foydalanish",
     *       "position": 1
     *     },
     *     "is_first_page": true,
     *     "is_last_page": false,
     *     "next": {
     *       "type": "page",
     *       "page_id": 2
     *     }
     *   }
     * }
     * @response 200 {
     *   "participation_id": 1,
     *   "status": "completed",
     *   "completed": true
     * }
     */
    public function show(Request $request, Participation $participation, SurveyFlow $flow): JsonResponse
    {
        $this->authorizeOwnership($request, $participation);

        if ($participation->status->isCompleted()) {
            return response()->json([
                'participation_id' => $participation->id,
                'status' => $participation->status->value,
                'completed' => true,
            ]);
        }

        $currentPage = $participation->currentPage;
        if (! $currentPage) {
            throw new ApiException('Current page not found.', 'NOT_FOUND', 404);
        }

        $step = $flow->getStep($participation->survey, $currentPage, $participation->locale);

        return response()->json([
            'participation_id' => $participation->id,
            'status' => $participation->status->value,
            'current_step' => $step,
        ]);
    }

    /**
     * Submit Page Answers
     *
     * Validates and saves answers for the given page. Advances participation to the next page determined by SurveyFlow.
     *
     * Important: The frontend MUST NOT calculate the next page; the backend SurveyFlow response dictates navigation.
     *
     * @authenticated
     *
     * @urlParam participation int required The ID of the participation session. Example: 1
     * @urlParam page int required The ID of the page being submitted. Example: 1
     *
     * @bodyParam answers object required Map of question IDs to answer objects. For single choice: `{"option_id": 1}`; multiple choice: `{"option_ids": [1, 2]}`; rating: `{"value": 5}`; text: `{"text": "Ajoyib xizmat"}`. Example: {"1": {"option_id": 1}}
     *
     * @response 200 {
     *   "success": true,
     *   "current_step": {
     *     "page": {
     *       "id": 2,
     *       "title": "Baholash",
     *       "position": 2,
     *       "questions": [
     *         {
     *           "id": 2,
     *           "type": "rating",
     *           "title": "Xizmat ko‘rsatish sifatini baholang (1-5)",
     *           "is_required": true,
     *           "position": 1,
     *           "settings": {"min": 1, "max": 5}
     *         }
     *       ]
     *     },
     *     "is_first_page": false,
     *     "is_last_page": true,
     *     "next": {
     *       "type": "complete"
     *     }
     *   }
     * }
     * @response 422 {
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "This question is required.",
     *     "fields": {
     *       "answers.1": ["This question is required."]
     *     }
     *   }
     * }
     */
    public function savePage(
        Request $request,
        Participation $participation,
        SurveyPage $page,
        SavePageAnswersAction $action,
    ): JsonResponse {
        $this->authorizeOwnership($request, $participation);

        $answers = (array) $request->input('answers', []);
        $result = $action->execute($participation, $page, $answers);

        return response()->json($result);
    }

    /**
     * Complete Participation
     *
     * Finalizes participation session and marks it COMPLETED. Idempotent: repeated calls on an already completed session return success safely.
     *
     * @authenticated
     *
     * @urlParam participation int required The ID of the participation session. Example: 1
     *
     * @response 200 {
     *   "completed": true,
     *   "thank_you_message": "Fikringiz uchun tashakkur!"
     * }
     */
    public function complete(
        Request $request,
        Participation $participation,
        CompleteParticipationAction $action,
    ): JsonResponse {
        $this->authorizeOwnership($request, $participation);

        $result = $action->execute($participation);

        return response()->json($result);
    }

    protected function authorizeOwnership(Request $request, Participation $participation): void
    {
        $user = $request->user();
        if (! $user || $participation->user_id !== $user->id) {
            throw new ApiException(
                message: 'Unauthorized access to participation.',
                errorCode: 'FORBIDDEN',
                statusCode: 403,
            );
        }
    }
}
