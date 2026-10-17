<?php

namespace App\Modules\Identity\Enums;

enum PlatformLocale: string
{
    case UZ = 'uz';
    case RU = 'ru';
    case EN = 'en';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
