<?php

namespace App\Enums;

enum LevelType: string
{
    case Shs     = 'shs';
    case College = 'college';

    public function label(): string
    {
        return match ($this) {
            self::Shs     => 'Senior High School',
            self::College => 'College',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
