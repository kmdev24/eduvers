<?php

namespace App\Enums;

enum TeacherType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Movers   = 'movers';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-Time',
            self::PartTime => 'Part-Time',
            self::Movers   => 'Movers Faculty',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
