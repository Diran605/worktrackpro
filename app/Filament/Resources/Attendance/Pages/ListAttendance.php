<?php

namespace App\Filament\Resources\Attendance\Pages;

use App\Filament\Resources\Attendance\AttendanceResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendance extends ListRecords
{
    protected static string $resource = AttendanceResource::class;
}

