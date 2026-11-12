<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\DocsFeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListDocsFeedback extends ListRecords
{
    protected static string $resource = DocsFeedbackResource::class;

    public function getSubheading(): ?string
    {
        return 'Answers to "Was this helpful?" from readers of the documentation library. Visitors are not identified.';
    }
}
