<?php

namespace App\Modules\Participation\Models;

use App\Modules\Survey\Models\SurveyQuestion;
use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $participation_id
 * @property int $question_id
 * @property string|null $text_value
 * @property float|null $number_value
 * @property array<string, mixed>|null $json_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'participation_id',
        'question_id',
        'text_value',
        'number_value',
        'json_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number_value' => 'float',
            'json_value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Participation, $this>
     */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    /**
     * @return BelongsTo<SurveyQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class);
    }

    /**
     * @return BelongsToMany<SurveyQuestionOption, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            SurveyQuestionOption::class,
            'answer_options',
            'answer_id',
            'option_id',
        );
    }
}
