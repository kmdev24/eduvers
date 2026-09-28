<?php

namespace App\Enums;

enum UserRole: string
{
    case Developer = 'developer';
    case Teacher   = 'teacher';
    case Student   = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer (Admin)',
            self::Teacher   => 'Teacher',
            self::Student   => 'Student',
        };
    }

    /** Named route of this role's home dashboard. */
    public function dashboardRoute(): string
    {
        return $this->value.'.dashboard';
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
