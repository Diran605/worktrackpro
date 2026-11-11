<?php

namespace App\Filament\Resources\OrganisationSettings\Pages;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use App\Services\OrganisationSettingsService;
use Filament\Resources\Pages\EditRecord;

class EditOrganisationSetting extends EditRecord
{
    protected static string $resource = OrganisationSettingResource::class;

    protected function afterSave(): void
    {
        app(OrganisationSettingsService::class)->clearCache((int) $this->record->organisation_id);
    }
}

