<?php

namespace App\Enums;

enum StopReason: string
{
    case Manual = 'manual';
    case ClockOut = 'clock_out';
    case SystemTimeout = 'system_timeout';
}

