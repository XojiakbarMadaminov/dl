<?php

namespace App\Modules\Results\Services;

use App\Modules\Results\Queries\SurveyParticipationsQuery;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\Survey;

class SurveyExportService
{
    public function __construct(protected SurveyParticipationsQuery $participationsQuery) {}

    /** @param array<string, mixed> $filters */
    public function exportCsv(Survey $survey, ?string $locale = null, array $filters = []): string
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

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array_map($this->escapeCell(...), $headers), escape: '');

        $participations = $this->participationsQuery->build($survey, $filters)
            ->with('answers.options')
            ->lazyById(100);

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

            fputcsv($output, array_map($this->escapeCell(...), $row), escape: '');
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }

    private function escapeCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $cell) === 1 ? "'".$cell : $cell;
    }
}
