<?php

namespace App\Filament\Resources\DocsAccessCodes\Pages;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocsAccessCodes extends ListRecords
{
    protected static string $resource = DocsAccessCodeResource::class;

    public function getSubheading(): ?string
    {
        return 'Give a code to a client or anyone outside the staff so they can read restricted manuals in the documentation library.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New access code'),
        ];
    }
}
