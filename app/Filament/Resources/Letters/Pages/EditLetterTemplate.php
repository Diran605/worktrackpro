<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\LetterTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditLetterTemplate extends EditRecord
{
    protected static string $resource = LetterTemplateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['last_edited_by'] = auth()->id();
        return $data;
    }
}

