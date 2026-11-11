<?php

namespace App\Filament\Resources\Attendance\Pages;

use App\Filament\Resources\Attendance\SessionReopenRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSessionReopenRequests extends ListRecords
{
    protected static string $resource = SessionReopenRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
