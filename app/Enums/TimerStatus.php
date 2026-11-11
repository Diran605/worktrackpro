<?php

namespace App\Enums;

enum TimerStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Paused = 'paused';
    case Stopped = 'stopped';
}

