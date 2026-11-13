<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Filament\Resources\Organisations\OrganisationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganisation extends EditRecord
{
    protected function getRedirectUrl(): string
    {
        // Single company mode has no organisations list: go back to Platform Settings.
        return \App\Support\Platform::isSingle()
            ? \App\Filament\Pages\PlatformSettings::getUrl()
            : $this->getResource()::getUrl('index');
    }

protected static string $resource = OrganisationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['modules'] = \App\Support\Modules::enabledFor($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['modules']); // saved separately below

        return $data;
    }

    protected function afterSave(): void
    {
        \App\Support\Modules::setFor($this->record, $this->data['modules'] ?? []);
    }
}

