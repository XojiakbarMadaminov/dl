<?php

namespace Tests\Unit;

use App\Modules\Participation\Services\SurveyLogicEvaluator;
use App\Modules\Survey\Models\SurveyLogicRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SurveyLogicEvaluatorTest extends TestCase
{
    public static function conditions(): array
    {
        return [
            'choice equals' => [3, 'equals', 3, true],
            'choice not equals' => [4, 'not_equals', 3, true],
            'choice equals is strict' => [4, 'equals', 3, false],
            'multiple contains' => [[3, 4], 'contains', 3, true],
            'multiple not contains' => [[4], 'not_contains', 3, true],
            'rating greater' => [4, 'greater_than', 3, true],
            'rating less' => [2, 'less_than', 3, true],
            'rating boundary' => [3, 'greater_than', 3, false],
            'text equals' => ['Salom', 'equals', 'Salom', true],
            'text case sensitive' => ['salom', 'equals', 'Salom', false],
            'text contains' => ['Salom dunyo', 'contains', 'dunyo', true],
            'text not contains' => ['Salom', 'not_contains', 'dunyo', true],
            'answered zero' => [0, 'answered', null, true],
            'not answered whitespace' => ['  ', 'not_answered', null, true],
            'not answered empty selections' => [[], 'not_answered', null, true],
            'missing is not unequal' => [null, 'not_equals', 3, false],
            'missing is not not contains' => [null, 'not_contains', 3, false],
            'missing is not answered' => [null, 'not_answered', null, true],
        ];
    }

    #[DataProvider('conditions')]
    public function test_condition_operators(mixed $actual, string $operator, mixed $value, bool $expected): void
    {
        $rule = new SurveyLogicRule([
            'match' => 'all', 'conditions' => [['question_id' => 1, 'operator' => $operator, 'value' => $value]],
        ]);
        $this->assertSame($expected, (new SurveyLogicEvaluator)->matches($rule, [1 => $actual]));
    }
}
