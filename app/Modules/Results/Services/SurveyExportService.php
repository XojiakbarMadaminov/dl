<?php

namespace App\Modules\Results\Services;

use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Participation\Models\Participation;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\Survey;

class SurveyExportService
{
    public function exportCsv(Survey $survey, ?string $locale = null): string
    {
        $locale = $locale ?? $survey->default_locale;

        // Load all questions across all pages ordered by page position and question position
        $pages = $survey->pages()->orderBy('position')->with(['questions' => fn ($q) => $q->orderBy('position')->with('options')])->get();
        $questions = [];
        foreach ($pages as $page) {
            foreach ($page->questions as $question) {
                $questions[] = $question;
            }
        }

        // CSV Headers
        $headers = [
            'Participation ID',
            'User ID',
            'Locale',
            'Started At',
            'Completed At',
        ];

        foreach ($questions as $q) {
            $title = $q->title[$locale] ?? ($q->title[$survey->default_locale] ?? "Q{$q->id}");
            $headers[] = "Q{$q->id}: ".str_replace(["\r", "\n"], ' ', $title);
        }

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, $headers);

        // Fetch completed participations with answers
        $participations = Participation::where('survey_id', $survey->id)
            ->where('status', ParticipationStatus::COMPLETED)
            ->with(['answers.options', 'user'])
            ->orderBy('completed_at')
            ->get();

        foreach ($participations as $participation) {
            $answersByQuestion = $participation->answers->keyBy('question_id');

            $row = [
                $participation->id,
                $participation->user_id,
                $participation->locale,
                $participation->started_at->toIso8601String(),
                $participation->completed_at ? $participation->completed_at->toIso8601String() : '',
            ];

            foreach ($questions as $q) {
                $ans = $answersByQuestion->get($q->id);
                if (! $ans) {
                    $row[] = '';

                    continue;
                }

                if ($q->type === QuestionType::RATING) {
                    $row[] = (string) $ans->number_value;
                } elseif (in_array($q->type, [QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT], true)) {
                    $row[] = (string) ($ans->text_value ?? '');
                } else {
                    // Choice options
                    $optionLabels = $ans->options->map(function ($opt) use ($locale, $survey) {
                        return $opt->label[$locale] ?? ($opt->label[$survey->default_locale] ?? (string) $opt->id);
                    })->all();
                    $row[] = implode(', ', $optionLabels);
                }
            }

            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }
}
