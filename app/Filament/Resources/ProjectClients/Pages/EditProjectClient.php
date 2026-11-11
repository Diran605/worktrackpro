<?php

namespace App\Filament\Resources\ProjectClients\Pages;

use App\Filament\Resources\ProjectClients\ProjectClientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectClient extends EditRecord
{
        protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = ProjectClientResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

