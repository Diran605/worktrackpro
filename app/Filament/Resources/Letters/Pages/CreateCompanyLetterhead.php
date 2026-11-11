<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\CompanyLetterheadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompanyLetterhead extends CreateRecord
{
    protected static string $resource = CompanyLetterheadResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = auth()->user()->organisation_id;
        return $data;
    }
}

