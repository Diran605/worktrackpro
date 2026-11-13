<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
        protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + RoleResource::permissionState($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Permissions are saved separately (grouped checklists).
        return collect($data)->reject(fn ($v, $k) => str_starts_with($k, 'perm_'))->all();
    }

    protected function afterSave(): void
    {
        RoleResource::syncPermissions($this->record, $this->data);
    }
}

