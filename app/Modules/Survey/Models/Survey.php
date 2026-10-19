<?php

namespace App\Modules\Survey\Models;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Survey\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $created_by
 * @property SurveyStatus $status
 * @property string $default_locale
 * @property array<int, string> $locales
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property array<string, string>|null $welcome_message
 * @property array<string, string>|null $thank_you_message
 * @property string|null $internal_note
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'created_by',
        'status',
        'default_locale',
        'locales',
        'title',
        'description',
        'welcome_message',
        'thank_you_message',
        'internal_note',
        'published_at',
        'closed_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
            'locales' => 'array',
            'title' => 'array',
            'description' => 'array',
            'welcome_message' => 'array',
            'thank_you_message' => 'array',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SurveyPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(SurveyPage::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<SurveyQuestion, SurveyPage, $this>
     */
    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(SurveyQuestion::class, SurveyPage::class, 'survey_id', 'page_id');
    }

    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw new ApiException(
                message: 'Active or locked survey cannot be edited.',
                errorCode: 'SURVEY_NOT_EDITABLE',
                statusCode: 422,
            );
        }
    }

    /** @return HasMany<SurveyLogicRule, $this> */
    public function logicRules(): HasMany
    {
        return $this->hasMany(SurveyLogicRule::class)->orderBy('priority')->orderBy('id');
    }
}
