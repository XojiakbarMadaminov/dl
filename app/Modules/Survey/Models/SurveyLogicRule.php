<?php

namespace App\Modules\Survey\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $survey_id
 * @property string $action
 * @property int|null $source_page_id
 * @property int|null $target_page_id
 * @property int|null $target_question_id
 * @property int $priority
 * @property string $match
 * @property array<int, array{question_id: int, operator: string, value?: mixed}> $conditions
 */
class SurveyLogicRule extends Model
{
    protected $fillable = [
        'survey_id', 'action', 'source_page_id', 'target_page_id',
        'target_question_id', 'priority', 'match', 'conditions',
    ];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }
}
