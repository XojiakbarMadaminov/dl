<?php

namespace App\Modules\Survey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $survey_id
 * @property int $position
 * @property array<string, string>|null $title
 * @property array<string, string>|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SurveyPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'position',
        'title',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'page_id')->orderBy('position');
    }
}
