<?php

namespace App\Filament\Resources\CompetencyChecks\Pages;

use App\Filament\Resources\CompetencyChecks\CompetencyCheckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompetencyChecks extends ListRecords
{
    protected static string $resource = CompetencyCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false),
        ];
    }
}
