<?php

namespace App\Modules\Participation\Models;

use App\Modules\Survey\Models\SurveyQuestionOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $answer_id
 * @property int $option_id
 */
class AnswerOption extends Model
{
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'answer_options';

    protected $fillable = [
        'answer_id',
        'option_id',
    ];

    /**
     * @return BelongsTo<Answer, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /**
     * @return BelongsTo<SurveyQuestionOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestionOption::class);
    }
}
