<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case CarriedOver = 'carried_over';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
            self::CarriedOver => 'Carried Over',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
            self::CarriedOver => 'info',
            self::Cancelled => 'danger',
        };
    }
}
