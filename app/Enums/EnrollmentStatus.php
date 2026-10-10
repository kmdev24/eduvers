<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Waiting for approval',
            self::Approved => 'Approved',
            self::Rejected => 'Not approved',
        };
    }
}
