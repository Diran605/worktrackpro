<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
        
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->organisation_id && !isset($data['organisation_id'])) {
            $data['organisation_id'] = $user->organisation_id;
        }
        if ($user && $user->department_id && !isset($data['department_id'])) {
            $data['department_id'] = $user->department_id;
        }
        return $data;
    }
protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;
}


