<?php

namespace App\Modules\Moderation\Enums;

enum ModerationDecision: string
{
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case CHANGES_REQUESTED = 'changes_requested';
    case REJECTED = 'rejected';
}
