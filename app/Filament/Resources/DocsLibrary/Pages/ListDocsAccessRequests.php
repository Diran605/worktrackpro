<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\DocsAccessRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDocsAccessRequests extends ListRecords
{
    protected static string $resource = DocsAccessRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'People who asked for access to a restricted manual. To give access, create an access code and send it to them.';
    }
}
