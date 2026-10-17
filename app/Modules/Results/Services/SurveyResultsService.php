<?php

namespace App\Modules\Results\Services;

use App\Exceptions\ApiException;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Answer;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyQuestion;
use Illuminate\Support\Facades\DB;

class SurveyResultsService
{
    /**
     * @return array<string, mixed>
     */
    public function getSummary(Survey $survey): array
    {
        $startedCount = Participation::where('survey_id', $survey->id)->count();
        $completedCount = Participation::where('survey_id', $survey->id)
            ->where('status', ParticipationStatus::COMPLETED)
            ->count();

        $inProgressCount = $startedCount - $completedCount;
        $completionRate = $startedCount > 0
            ? round(($completedCount / $startedCount) * 100, 1)
            : 0.0;

        return [
            'survey_id' => $survey->id,
            'started_count' => $startedCount,
            'completed_count' => $completedCount,
            'in_progress_count' => $inProgressCount,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuestionAggregate(Survey $survey, SurveyQuestion $question, ?string $locale = null): array
    {
        $surveyPageIds = $survey->pages()->pluck('id')->all();
        if (! in_array($question->page_id, $surveyPageIds, true)) {
            throw new ApiException('Question does not belong to survey.', 'NOT_FOUND', 404);
        }

        $locale = $locale ?? $survey->default_locale;
        $question->loadMissing('options');

        $participationIds = Participation::where('survey_id', $survey->id)
            ->pluck('id');

        $totalAnswers = Answer::whereIn('participation_id', $participationIds)
            ->where('question_id', $question->id)
            ->count();

        $data = [
            'question_id' => $question->id,
            'type' => $question->type->value,
            'title' => $question->title[$locale] ?? ($question->title[$survey->default_locale] ?? ''),
            'total_responses' => $totalAnswers,
        ];

        switch ($question->type) {
            case QuestionType::SINGLE_CHOICE:
            case QuestionType::MULTIPLE_CHOICE:
                $optionCounts = DB::table('answer_options')
                    ->join('answers', 'answer_options.answer_id', '=', 'answers.id')
                    ->whereIn('answers.participation_id', $participationIds)
                    ->where('answers.question_id', $question->id)
                    ->select('answer_options.option_id', DB::raw('count(*) as count'))
                    ->groupBy('answer_options.option_id')
                    ->pluck('count', 'option_id')
                    ->all();

                $distribution = [];
                foreach ($question->options as $option) {
                    $cnt = (int) ($optionCounts[$option->id] ?? 0);
                    $pct = $totalAnswers > 0 ? round(($cnt / $totalAnswers) * 100, 1) : 0.0;

                    $distribution[] = [
                        'option_id' => $option->id,
                        'label' => $option->label[$locale] ?? ($option->label[$survey->default_locale] ?? ''),
                        'count' => $cnt,
                        'percentage' => $pct,
                    ];
                }

                $data['distribution'] = $distribution;
                break;

            case QuestionType::RATING:
                $avgRating = Answer::whereIn('participation_id', $participationIds)
                    ->where('question_id', $question->id)
                    ->avg('number_value');

                $counts = Answer::whereIn('participation_id', $participationIds)
                    ->where('question_id', $question->id)
                    ->select('number_value', DB::raw('count(*) as count'))
                    ->groupBy('number_value')
                    ->pluck('count', 'number_value')
                    ->all();

                $min = (int) ($question->settings['min'] ?? 1);
                $max = (int) ($question->settings['max'] ?? 5);

                $distribution = [];
                for ($val = $min; $val <= $max; $val++) {
                    $cnt = (int) ($counts[(string) $val] ?? 0);
                    $pct = $totalAnswers > 0 ? round(($cnt / $totalAnswers) * 100, 1) : 0.0;
                    $distribution[] = [
                        'rating' => $val,
                        'count' => $cnt,
                        'percentage' => $pct,
                    ];
                }

                $data['average'] = $avgRating !== null ? round((float) $avgRating, 2) : 0.0;
                $data['distribution'] = $distribution;
                break;

            case QuestionType::SHORT_TEXT:
            case QuestionType::LONG_TEXT:
                $latestAnswers = Answer::whereIn('participation_id', $participationIds)
                    ->where('question_id', $question->id)
                    ->whereNotNull('text_value')
                    ->latest()
                    ->limit(50)
                    ->pluck('text_value')
                    ->all();

                $data['sample_answers'] = $latestAnswers;
                break;
        }

        return $data;
    }
}
