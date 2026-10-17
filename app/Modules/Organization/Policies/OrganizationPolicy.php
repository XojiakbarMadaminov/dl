<?php

namespace App\Modules\Organization\Policies;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Models\Organization;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $organization->hasMember($user);
    }

    public function update(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role !== null && $role->canManageOrganization();
    }

    public function delete(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role === OrganizationRole::OWNER;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role !== null && $role->canManageOrganization();
    }

    public function createSurvey(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role !== null && $role->canEditSurveys();
    }

    public function editSurvey(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role !== null && $role->canEditSurveys();
    }
}
