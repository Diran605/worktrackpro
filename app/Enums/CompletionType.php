<?php

namespace App\Enums;

enum CompletionType: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Attempted = 'attempted';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Complete',
            self::Partial => 'Partial',
            self::Attempted => 'Attempted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Complete => 'success',
            self::Partial => 'warning',
            self::Attempted => 'danger',
        };
    }
}
