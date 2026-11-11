<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case SystemClosed = 'system_closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::SystemClosed => 'System Closed',
        };
    }
}

