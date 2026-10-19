<?php

namespace App\Modules\Survey\Validators;

use App\Exceptions\ApiException;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\Survey;

class SurveyLogicValidator
{
    /** @param array<int, array<string, mixed>> $rules */
    public function validate(Survey $survey, array $rules): void
    {
        $pages = $survey->pages()->with('questions.options')->orderBy('id')->get();
        $questions = $pages->flatMap(fn ($page) => $page->questions)->keyBy('id');
        $pageOrder = $pages->pluck('id')->flip()->all();
        $targets = [];

        foreach ($rules as $index => $rule) {
            $field = "rules.{$index}";
            $visibility = $rule['action'] === 'show_question';
            if ($visibility) {
                $target = $questions->get($rule['target_question_id'] ?? 0);
                if (! $target || isset($targets[$target->id]) || ! empty($rule['source_page_id']) || ! empty($rule['target_page_id'])) {
                    $this->fail($field, 'Visibility requires one unique target question in this survey and no page targets.');
                }
                $targets[$target->id] = true;
                $evaluationPageId = $target->page_id;
            } else {
                $evaluationPageId = $rule['source_page_id'] ?? 0;
                if (! array_key_exists($evaluationPageId, $pageOrder) || ! empty($rule['target_question_id'])) {
                    $this->fail($field, 'Navigation requires a source page in this survey and no question target.');
                }
                if ($rule['action'] === 'skip_to_page') {
                    $destination = $rule['target_page_id'] ?? 0;
                    if (! array_key_exists($destination, $pageOrder) || $pageOrder[$destination] <= $pageOrder[$evaluationPageId]) {
                        $this->fail($field, 'Navigation may only target a later page in this survey.');
                    }
                } elseif (! empty($rule['target_page_id'])) {
                    $this->fail($field, 'Completion must not have a target page.');
                }
            }

            foreach ($rule['conditions'] as $conditionIndex => $condition) {
                $conditionField = "{$field}.conditions.{$conditionIndex}";
                $question = $questions->get($condition['question_id']);
                if (! $question || $pageOrder[$question->page_id] > $pageOrder[$evaluationPageId] || ($visibility && $pageOrder[$question->page_id] === $pageOrder[$evaluationPageId])) {
                    $this->fail($conditionField, 'Conditions must reference this survey: earlier pages for visibility, earlier/current page for navigation.');
                }

                $operator = $condition['operator'];
                $value = $condition['value'] ?? null;
                if (in_array($operator, ['answered', 'not_answered'], true)) {
                    if ($value !== null) {
                        $this->fail($conditionField, 'Answered operators must not have a value.');
                    }

                    continue;
                }

                $allowed = match ($question->type) {
                    QuestionType::SINGLE_CHOICE => ['equals', 'not_equals'],
                    QuestionType::MULTIPLE_CHOICE => ['contains', 'not_contains'],
                    QuestionType::RATING => ['equals', 'not_equals', 'greater_than', 'less_than'],
                    QuestionType::SHORT_TEXT, QuestionType::LONG_TEXT => ['equals', 'not_equals', 'contains', 'not_contains'],
                };
                if (! in_array($operator, $allowed, true)) {
                    $this->fail($conditionField, 'Operator is not supported by this question type.');
                }
                if ($question->type->requiresOptions()) {
                    if (! is_int($value) || ! $question->options->contains('id', $value)) {
                        $this->fail($conditionField, 'Value must be an integer option ID belonging to the condition question.');
                    }
                } elseif ($question->type === QuestionType::RATING) {
                    if (! is_int($value) || $value < ($question->settings['min'] ?? 1) || $value > ($question->settings['max'] ?? 5)) {
                        $this->fail($conditionField, 'Value must be an integer within the rating bounds.');
                    }
                } elseif (! is_string($value) || trim($value) === '' || mb_strlen($value) > ($question->settings['max_length'] ?? ($question->type === QuestionType::SHORT_TEXT ? 255 : 5000))) {
                    $this->fail($conditionField, 'Value must be nonempty text within the question length limit.');
                }
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ApiException('Invalid survey logic.', 'INVALID_SURVEY_LOGIC', 422, [$field => [$message]]);
    }
}
