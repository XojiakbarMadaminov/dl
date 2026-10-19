<?php

namespace App\Modules\Participation\Models;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Participation\Enums\ParticipationStatus;
use App\Modules\Survey\Models\Survey;
use App\Modules\Survey\Models\SurveyPage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $survey_id
 * @property int $user_id
 * @property string $locale
 * @property ParticipationStatus $status
 * @property int|null $current_page_id
 * @property array<int, int> $page_history
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Participation extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'user_id',
        'locale',
        'status',
        'current_page_id',
        'page_history',
        'started_at',
        'completed_at',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'page_history' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SurveyPage, $this>
     */
    public function currentPage(): BelongsTo
    {
        return $this->belongsTo(SurveyPage::class, 'current_page_id');
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function assertCanAcceptAnswers(): void
    {
        if ($this->status->isCompleted()) {
            throw new ApiException(
                message: 'Participation has already been completed.',
                errorCode: 'PARTICIPATION_ALREADY_COMPLETED',
                statusCode: 422,
            );
        }

        if (! $this->survey->status->isActive()) {
            throw new ApiException(
                message: 'Survey is no longer active.',
                errorCode: 'SURVEY_NOT_ACTIVE',
                statusCode: 422,
            );
        }
    }
}
