<?php

namespace App\Enums;

enum WorkType: string
{
    case Direct = 'direct';
    case Indirect = 'indirect';
    case Growth = 'growth';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Indirect => 'Indirect',
            self::Growth => 'Growth',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Direct => 'success',
            self::Indirect => 'warning',
            self::Growth => 'info',
        };
    }
}
