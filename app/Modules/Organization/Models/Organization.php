<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $inn
 * @property int $created_by
 * @property OrganizationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inn',
        'created_by',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role', 'joined_at']);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function roleOf(User $user): ?OrganizationRole
    {
        /** @var OrganizationMember|null $member */
        $member = $this->members()->where('user_id', $user->id)->first();

        return $member?->role;
    }
}
