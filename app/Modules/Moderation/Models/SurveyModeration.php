<?php

namespace App\Modules\Moderation\Models;

use App\Models\User;
use App\Modules\Moderation\Enums\ModerationDecision;
use App\Modules\Survey\Models\Survey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $survey_id
 * @property int|null $moderator_id
 * @property ModerationDecision $decision
 * @property string|null $comment
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SurveyModeration extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'moderator_id',
        'decision',
        'comment',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ModerationDecision::class,
            'decided_at' => 'datetime',
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
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
