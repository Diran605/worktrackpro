<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\ManualReleaseResource;
use Filament\Resources\Pages\ListRecords;

class ListManualReleases extends ListRecords
{
    protected static string $resource = ManualReleaseResource::class;

    public function getSubheading(): ?string
    {
        return 'Approved changes are live straight away. Owners group them into a version with "Publish version" under Knowledge > Systems.';
    }
}
