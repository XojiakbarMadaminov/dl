<?php

namespace App\Modules\Identity\Enums;

enum PlatformRole: string
{
    case USER = 'user';
    case ADMIN = 'admin';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}
