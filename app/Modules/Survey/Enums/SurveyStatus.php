<?php

namespace App\Modules\Survey\Enums;

enum SurveyStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case CHANGES_REQUESTED = 'changes_requested';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::CHANGES_REQUESTED], true);
    }

    public function isReviewable(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
