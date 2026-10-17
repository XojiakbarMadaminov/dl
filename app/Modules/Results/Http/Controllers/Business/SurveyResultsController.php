<?php

namespace App\Modules\Results\Http\Controllers\Business;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Organization;
use App\Modules\Participation\Models\Participation;
use App\Modules\Results\Services\SurveyExportService;
use App\Modules\Results\Services\SurveyResultsService;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Business
 *
 * @subgroup Results & Analytics
 *
 * Real-time analytics, response aggregates, respondent participation breakdown, and CSV data export.
 */
class SurveyResultsController extends Controller
{
    /**
     * Survey Summary Metrics
     *
     * Returns high-level metrics: total starts, completions, in-progress count, and completion rate percentage.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @response 200 {
     *   "survey_id": 1,
     *   "started_count": 10,
     *   "completed_count": 8,
     *   "in_progress_count": 2,
     *   "completion_rate": 80.0
     * }
     */
    public function summary(
        Request $request,
        Organization $organization,
        Survey $survey,
        SurveyResultsService $service,
    ): JsonResponse {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $summary = $service->getSummary($survey);

        return response()->json($summary);
    }

    /**
     * Question Response Aggregates
     *
     * Returns distribution counts and percentages for choice options, or average rating score and count for rating questions.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     * @urlParam question int required The ID of the question. Example: 1
     *
     * @queryParam locale string Optional locale to label choice options. Defaults to survey default_locale. Example: uz
     *
     * @response 200 {
     *   "question_id": 1,
     *   "type": "single_choice",
     *   "title": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
     *   "total_answers": 8,
     *   "options": [
     *     {
     *       "id": 1,
     *       "label": "Har kuni",
     *       "count": 6,
     *       "percentage": 75.0
     *     },
     *     {
     *       "id": 2,
     *       "label": "Haftada bir necha marta",
     *       "count": 2,
     *       "percentage": 25.0
     *     }
     *   ]
     * }
     */
    public function questionAggregate(
        Request $request,
        Organization $organization,
        Survey $survey,
        SurveyQuestion $question,
        SurveyResultsService $service,
    ): JsonResponse {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $locale = $request->query('locale');
        $aggregate = $service->getQuestionAggregate(
            survey: $survey,
            question: $question,
            locale: is_string($locale) ? $locale : null,
        );

        return response()->json($aggregate);
    }

    /**
     * List Participations
     *
     * List all respondent participations for the survey with pagination and status filtering.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @queryParam status string Filter by status (in_progress, completed, abandoned). Example: completed
     * @queryParam page int Page number. Example: 1
     *
     * @response 200 {
     *   "current_page": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "survey_id": 1,
     *       "user_id": 3,
     *       "locale": "uz",
     *       "status": "completed",
     *       "started_at": "2026-10-17T10:00:00.000000Z",
     *       "completed_at": "2026-10-17T10:05:00.000000Z",
     *       "user": {
     *         "id": 3,
     *         "name": "Anvar Alimov",
     *         "email": "respondent@dialog.uz"
     *       }
     *     }
     *   ],
     *   "total": 1,
     *   "per_page": 20
     * }
     */
    public function participations(
        Request $request,
        Organization $organization,
        Survey $survey,
    ): JsonResponse {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $status = $request->query('status');

        $paginated = Participation::where('survey_id', $survey->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('user')
            ->latest('started_at')
            ->paginate(20);

        return response()->json($paginated);
    }

    /**
     * Respondent Participation Detail
     *
     * Retrieve individual respondent's answers across all questions in the survey.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     * @urlParam participation int required The ID of the participation session. Example: 1
     *
     * @response 200 {
     *   "participation": {
     *     "id": 1,
     *     "status": "completed",
     *     "locale": "uz",
     *     "started_at": "2026-10-17T10:00:00.000000Z",
     *     "completed_at": "2026-10-17T10:05:00.000000Z",
     *     "user": {
     *       "id": 3,
     *       "name": "Anvar Alimov",
     *       "email": "respondent@dialog.uz"
     *     },
     *     "answers": [
     *       {
     *         "question_id": 1,
     *         "question_title": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
     *         "type": "single_choice",
     *         "text_value": "Har kuni",
     *         "number_value": null,
     *         "selected_options": [
     *           {"id": 1, "label": "Har kuni"}
     *         ]
     *       }
     *     ]
     *   }
     * }
     */
    public function participationDetail(
        Request $request,
        Organization $organization,
        Survey $survey,
        Participation $participation,
    ): JsonResponse {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        if ($participation->survey_id !== $survey->id) {
            throw new ApiException('Participation does not belong to survey.', 'NOT_FOUND', 404);
        }

        $participation->load(['user', 'answers.question', 'answers.options']);

        return response()->json([
            'participation' => [
                'id' => $participation->id,
                'status' => $participation->status->value,
                'locale' => $participation->locale,
                'started_at' => $participation->started_at,
                'completed_at' => $participation->completed_at,
                'user' => [
                    'id' => $participation->user->id,
                    'name' => $participation->user->name,
                    'email' => $participation->user->email,
                ],
                'answers' => $participation->answers->map(fn ($ans) => [
                    'question_id' => $ans->question_id,
                    'question_title' => $ans->question->title[$participation->locale] ?? ($ans->question->title[$survey->default_locale] ?? ''),
                    'type' => $ans->question->type->value,
                    'text_value' => $ans->text_value,
                    'number_value' => $ans->number_value,
                    'selected_options' => $ans->options->map(fn ($opt) => [
                        'id' => $opt->id,
                        'label' => $opt->label[$participation->locale] ?? ($opt->label[$survey->default_locale] ?? ''),
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * Export Results CSV
     *
     * Download completed survey responses as a CSV spreadsheet.
     *
     * @authenticated
     *
     * @urlParam organization int required The ID of the organization. Example: 1
     * @urlParam survey int required The ID of the survey. Example: 1
     *
     * @queryParam locale string Optional locale for question and option headers. Defaults to survey default_locale. Example: uz
     *
     * @response 200 {
     *   "description": "Returns raw text/csv attachment"
     * }
     */
    public function export(
        Request $request,
        Organization $organization,
        Survey $survey,
        SurveyExportService $exportService,
    ): Response {
        $this->authorize('view', $organization);
        $this->validateBelongsToOrg($survey, $organization);

        $locale = $request->query('locale');
        $csv = $exportService->exportCsv($survey, is_string($locale) ? $locale : null);

        $filename = "survey_{$survey->id}_results_".now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function validateBelongsToOrg(Survey $survey, Organization $organization): void
    {
        if ($survey->organization_id !== $organization->id) {
            throw new ApiException('Survey does not belong to this organization.', 'NOT_FOUND', 404);
        }
    }
}
