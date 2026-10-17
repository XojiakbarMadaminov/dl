<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;

class CreateOrganizationAction
{
    /**
     * Atomically creates an organization and assigns the creator as owner.
     */
    public function execute(string $name, User $creator): Organization
    {
        return DB::transaction(function () use ($name, $creator) {
            $organization = Organization::create([
                'name' => trim($name),
                'created_by' => $creator->id,
                'status' => OrganizationStatus::ACTIVE,
            ]);

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
