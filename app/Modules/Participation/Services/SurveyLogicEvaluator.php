<?php

namespace App\Modules\Participation\Services;

use App\Modules\Survey\Models\SurveyLogicRule;

class SurveyLogicEvaluator
{
    /** @param array<int|string, mixed> $answers */
    public function matches(SurveyLogicRule $rule, array $answers): bool
    {
        $matches = [];
        foreach ($rule->conditions as $condition) {
            $actual = $answers[$condition['question_id']] ?? null;
            $expected = $condition['value'] ?? null;
            $answered = $actual !== null && $actual !== [] && (! is_string($actual) || trim($actual) !== '');
            $operator = $condition['operator'];
            $matches[] = match ($operator) {
                'answered' => $answered,
                'not_answered' => ! $answered,
                'equals' => $answered && $actual === $expected,
                'not_equals' => $answered && $actual !== $expected,
                'contains' => $answered && (is_array($actual) ? in_array($expected, $actual, true) : (is_string($actual) && is_string($expected) && str_contains($actual, $expected))),
                'not_contains' => $answered && (is_array($actual) ? ! in_array($expected, $actual, true) : (is_string($actual) && is_string($expected) && ! str_contains($actual, $expected))),
                'greater_than' => $answered && is_numeric($actual) && is_numeric($expected) && $actual > $expected,
                'less_than' => $answered && is_numeric($actual) && is_numeric($expected) && $actual < $expected,
                default => false,
            };
        }

        return $matches !== [] && ($rule->match === 'any' ? in_array(true, $matches, true) : ! in_array(false, $matches, true));
    }
}
