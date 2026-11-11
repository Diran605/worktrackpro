<?php

namespace App\Filament\Resources\WorkTypes\Pages;

use App\Filament\Resources\WorkTypes\WorkTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkType extends CreateRecord
{
        
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->organisation_id && !isset($data['organisation_id'])) {
            $data['organisation_id'] = $user->organisation_id;
        }
        return $data;
    }
protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = WorkTypeResource::class;

    protected static bool $canCreateAnother = false;
}


