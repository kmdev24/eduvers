<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case Teachers = 'teachers';
    case Students = 'students';
    case Section  = 'section';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'School-wide',
            self::Teachers => 'All teachers',
            self::Students => 'All students',
            self::Section  => 'Section',
        };
    }
}
