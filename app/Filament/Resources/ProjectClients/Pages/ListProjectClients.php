<?php

namespace App\Filament\Resources\ProjectClients\Pages;

use App\Filament\Resources\ProjectClients\ProjectClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectClients extends ListRecords
{
    protected static string $resource = ProjectClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
