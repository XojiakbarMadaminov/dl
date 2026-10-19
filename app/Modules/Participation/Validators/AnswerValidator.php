<?php

namespace App\Modules\Participation\Validators;

use App\Exceptions\ApiException;
use App\Modules\Survey\Enums\QuestionType;
use App\Modules\Survey\Models\SurveyPage;
use App\Modules\Survey\Models\SurveyQuestion;

class AnswerValidator
{
    /**
     * @param  array<int|string, mixed>  $answers  Array keyed by question_id
     * @return array<string, array<int, string>>
     */
    public function validatePageAnswers(SurveyPage $page, array $answers, bool $throw = true, ?array $applicableQuestionIds = null): array
    {
        $page->loadMissing(['questions.options']);
        $fieldErrors = [];

        foreach ($page->questions as $question) {
            if ($applicableQuestionIds !== null && ! in_array($question->id, $applicableQuestionIds, true)) {
                continue;
            }
            $questionKey = (string) $question->id;
            $hasAnswer = array_key_exists($questionKey, $answers) || array_key_exists($question->id, $answers);
            $value = $hasAnswer ? ($answers[$questionKey] ?? $answers[$question->id]) : null;

            $errors = $this->validateQuestionAnswer($question, $value);
            if (! empty($errors)) {
                $fieldErrors["answers.{$question->id}"] = $errors;
            }
        }

        if ($throw && ! empty($fieldErrors)) {
            throw new ApiException(
                message: 'One or more answers are invalid.',
                errorCode: 'INVALID_ANSWER',
                statusCode: 422,
                fields: $fieldErrors,
            );
        }

        return $fieldErrors;
    }

    /**
     * @return array<int, string>
     */
    public function validateQuestionAnswer(SurveyQuestion $question, mixed $value): array
    {
        $isEmpty = $value === null || (is_string($value) && trim($value) === '') || $value === [];

        if ($question->is_required && $isEmpty) {
            return ['This question is required.'];
        }

        if ($isEmpty) {
            return [];
        }

        $errors = [];

        switch ($question->type) {
            case QuestionType::SINGLE_CHOICE:
                $optionIds = $question->options->pluck('id')->all();
                if ((! is_int($value) && ! is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || ! in_array((int) $value, $optionIds, true)) {
                    $errors[] = 'Selected option is invalid.';
                }
                break;

            case QuestionType::MULTIPLE_CHOICE:
                if (! is_array($value)) {
                    $errors[] = 'Answer must be an array of selected options.';
                    break;
                }

                $optionIds = $question->options->pluck('id')->all();
                foreach ($value as $selectedValue) {
                    if (! is_int($selectedValue) && ! is_string($selectedValue)) {
                        return ['Selected options must be integer IDs.'];
                    }
                    if (filter_var($selectedValue, FILTER_VALIDATE_INT) === false) {
                        return ['Selected options must be integer IDs.'];
                    }
                }
                $selectedIds = array_map('intval', $value);

                if (count(array_unique($selectedIds)) !== count($selectedIds)) {
                    $errors[] = 'Selected options must be distinct.';
                }

                foreach ($selectedIds as $selectedId) {
                    if (! in_array($selectedId, $optionIds, true)) {
                        $errors[] = "Option ID {$selectedId} is not a valid option for this question.";
                    }
                }

                $minSelections = $question->settings['min_selections'] ?? null;
                if ($minSelections !== null && count($selectedIds) < (int) $minSelections) {
                    $errors[] = "You must select at least {$minSelections} option(s).";
                }

                $maxSelections = $question->settings['max_selections'] ?? null;
                if ($maxSelections !== null && count($selectedIds) > (int) $maxSelections) {
                    $errors[] = "You may select at most {$maxSelections} option(s).";
                }
                break;

            case QuestionType::RATING:
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value !== floor((float) $value)) {
                    $errors[] = 'Rating must be an integer value.';
                    break;
                }

                $ratingVal = (int) $value;
                $min = (int) ($question->settings['min'] ?? 1);
                $max = (int) ($question->settings['max'] ?? 5);

                if ($ratingVal < $min || $ratingVal > $max) {
                    $errors[] = "Rating must be between {$min} and {$max}.";
                }
                break;

            case QuestionType::SHORT_TEXT:
                if (! is_string($value)) {
                    $errors[] = 'Answer must be text.';
                    break;
                }

                $maxLength = (int) ($question->settings['max_length'] ?? 255);
                if (mb_strlen($value) > $maxLength) {
                    $errors[] = "Text exceeds maximum allowed length of {$maxLength} characters.";
                }
                break;

            case QuestionType::LONG_TEXT:
                if (! is_string($value)) {
                    $errors[] = 'Answer must be text.';
                    break;
                }

                $maxLength = (int) ($question->settings['max_length'] ?? 5000);
                if (mb_strlen($value) > $maxLength) {
                    $errors[] = "Text exceeds maximum allowed length of {$maxLength} characters.";
                }
                break;
        }

        return $errors;
    }
}
