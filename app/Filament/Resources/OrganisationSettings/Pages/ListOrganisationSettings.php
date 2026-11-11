<?php

namespace App\Filament\Resources\OrganisationSettings\Pages;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationSettings extends ListRecords
{
    protected static string $resource = OrganisationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->createAnother(false),
        ];
    }
}

