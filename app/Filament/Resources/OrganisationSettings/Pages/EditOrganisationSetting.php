<?php

namespace App\Filament\Resources\OrganisationSettings\Pages;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use App\Services\OrganisationSettingsService;
use Filament\Resources\Pages\EditRecord;

class EditOrganisationSetting extends EditRecord
{
    protected static string $resource = OrganisationSettingResource::class;

    public function getTitle(): string
    {
        return 'Settings for ' . ($this->record->organisation?->name ?? 'organisation');
    }

    public function getSubheading(): ?string
    {
        return 'These settings apply to everyone in this organisation. Changes take effect straight away.';
    }

    protected function afterSave(): void
    {
        app(OrganisationSettingsService::class)->clearCache((int) $this->record->organisation_id);
    }

    protected function getRedirectUrl(): ?string
    {
        // Stay on the form after saving.
        return null;
    }
}
