<?php

namespace App\Enums;

enum UserRole: string
{
    case Developer = 'developer';
    case Admin     = 'admin';
    case Teacher   = 'teacher';
    case Student   = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer',
            self::Admin     => 'Admin',
            self::Teacher   => 'Teacher',
            self::Student   => 'Student',
        };
    }

    /** Named route of this role's home dashboard. */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Developer => 'admin.dashboard',
            default         => $this->value.'.dashboard',
        };
    }

    /** Roles that share the admin area (admin + developer). */
    public function isAdminLevel(): bool
    {
        return $this === self::Admin || $this === self::Developer;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
