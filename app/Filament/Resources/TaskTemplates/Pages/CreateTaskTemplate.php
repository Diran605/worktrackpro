<?php

namespace App\Filament\Resources\TaskTemplates\Pages;

use App\Filament\Resources\TaskTemplates\TaskTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskTemplate extends CreateRecord
{
    protected static string $resource = TaskTemplateResource::class;
    
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = auth()->user()->organisation_id;
        $data['created_by'] = auth()->id();
        return $data;
    }
}

