<?php

namespace App\Modules\Survey\Policies;

use App\Models\User;
use App\Modules\Survey\Models\Survey;

class SurveyPolicy
{
    public function view(User $user, Survey $survey): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $survey->organization->hasMember($user);
    }

    public function create(User $user, Survey $survey): bool
    {
        $role = $survey->organization->roleOf($user);

        return $role !== null && $role->canEditSurveys();
    }

    public function update(User $user, Survey $survey): bool
    {
        $role = $survey->organization->roleOf($user);

        return $role !== null && $role->canEditSurveys();
    }

    public function delete(User $user, Survey $survey): bool
    {
        $role = $survey->organization->roleOf($user);

        return $role !== null && $role->canManageOrganization();
    }
}
