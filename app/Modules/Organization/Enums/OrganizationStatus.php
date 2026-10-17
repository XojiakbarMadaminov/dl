<?php

namespace App\Modules\Organization\Enums;

enum OrganizationStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
}
