<?php

namespace App\Enums;

/**
 * Track categories. The database column is a plain string, so adding a new
 * category only needs a new case here (plus label/description).
 */
enum TrackCategory: string
{
    case Academic = 'academic';
    case TechPro  = 'techpro';
    case Tvl      = 'tvl';
    case College  = 'college';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Academic',
            self::TechPro  => 'TechPro',
            self::Tvl      => 'TVL',
            self::College  => 'College',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Academic => 'SHS academic strands such as ABM, HUMSS and STEM.',
            self::TechPro  => 'SHS technical-professional strands such as ICT.',
            self::Tvl      => 'SHS technical-vocational-livelihood strands.',
            self::College  => 'College programs such as HRS (2-year bundle).',
        };
    }

    /** Whether this category belongs to College (vs Senior High School). */
    public function isCollege(): bool
    {
        return $this === self::College;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
