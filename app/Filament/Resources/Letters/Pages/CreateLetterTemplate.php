<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\LetterTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLetterTemplate extends CreateRecord
{
    protected static string $resource = LetterTemplateResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = auth()->user()->organisation_id;
        $data['created_by'] = auth()->id();
        return $data;
    }
}

