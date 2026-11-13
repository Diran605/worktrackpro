<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
        protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = RoleResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';

        // Roles made by organisation admins belong to their organisation only.
        if (!auth()->user()->hasRole('super_admin')) {
            $data['organisation_id'] = auth()->user()->organisation_id;
        }

        // Permissions are saved separately (grouped checklists).
        return collect($data)->reject(fn ($v, $k) => str_starts_with($k, 'perm_'))->all();
    }

    protected function afterCreate(): void
    {
        RoleResource::syncPermissions($this->record, $this->data);
    }
}

