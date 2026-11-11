<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuditLog extends CreateRecord
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

protected static string $resource = AuditLogResource::class;
}


