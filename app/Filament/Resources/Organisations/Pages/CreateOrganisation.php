<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Filament\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisation extends CreateRecord
{
        protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = OrganisationResource::class;

    protected static bool $canCreateAnother = false;

    /** Typing the address doesn't help either: no new organisations in single company mode. */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(\App\Support\Platform::isMulti(), 403);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['modules']); // saved in afterCreate()

        return $data;
    }

    protected function afterCreate(): void
    {
        \App\Support\Modules::setFor($this->record, $this->data['modules'] ?? []);
    }
}

