<?php

namespace App\Filament\Resources\ManualChanges\Pages;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListManualChangeRequests extends ListRecords
{
    protected static string $resource = ManualChangeRequestResource::class;
}
