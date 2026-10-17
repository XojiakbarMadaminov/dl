<?php

namespace App\Modules\Survey\Enums;

enum QuestionType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case SHORT_TEXT = 'short_text';
    case LONG_TEXT = 'long_text';
    case RATING = 'rating';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresOptions(): bool
    {
        return in_array($this, [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE], true);
    }
}
