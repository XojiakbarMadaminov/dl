<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrganizationAction
{
    /**
     * Atomically creates an organization and assigns the creator as owner.
     */
    public function execute(string $name, User $creator, string $inn): Organization
    {
        return DB::transaction(function () use ($name, $creator, $inn) {
            try {
                $organization = Organization::create([
                    'name' => trim($name),
                    'inn' => trim($inn),
                    'created_by' => $creator->id,
                    'status' => OrganizationStatus::ACTIVE,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['inn' => 'The inn has already been taken.']);
            }

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $creator->id,
                'role' => OrganizationRole::OWNER,
                'joined_at' => now(),
            ]);

            return $organization->load('members');
        });
    }
}
