<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\ManualReleaseResource;
use Filament\Resources\Pages\ListRecords;

class ListManualReleases extends ListRecords
{
    protected static string $resource = ManualReleaseResource::class;

    public function getSubheading(): ?string
    {
        return 'Approved changes reach staff straight away, and the documentation library when an owner publishes a version ("Publish version" under Knowledge > Systems, or "Publish now" when approving).';
    }
}
