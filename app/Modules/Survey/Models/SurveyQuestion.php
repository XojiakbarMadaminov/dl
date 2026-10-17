<?php

namespace App\Modules\Survey\Models;

use App\Modules\Survey\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property QuestionType $type
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property bool $is_required
 * @property int $position
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SurveyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'type',
        'title',
        'description',
        'is_required',
        'position',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'title' => 'array',
            'description' => 'array',
            'is_required' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SurveyPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SurveyPage::class, 'page_id');
    }

    /**
     * @return HasMany<SurveyQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(SurveyQuestionOption::class, 'question_id')->orderBy('position');
    }
}
