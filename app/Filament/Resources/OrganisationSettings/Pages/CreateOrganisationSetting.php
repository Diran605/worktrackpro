<?php

namespace App\Filament\Resources\OrganisationSettings\Pages;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisationSetting extends CreateRecord
{
    protected static string $resource = OrganisationSettingResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin')) {
            $data['organisation_id'] = $user->organisation_id;
        }
        return $data;
    }
}

