<?php

namespace App\Modules\Organization\Enums;

enum OrganizationRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case RESEARCHER = 'researcher';
    case VIEWER = 'viewer';

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function canManageOrganization(): bool
    {
        return in_array($this, [self::OWNER, self::MANAGER], true);
    }

    public function canEditSurveys(): bool
    {
        return in_array($this, [self::OWNER, self::MANAGER, self::RESEARCHER], true);
    }

    public function canViewSurveys(): bool
    {
        return true;
    }
}
