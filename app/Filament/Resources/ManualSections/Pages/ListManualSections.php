<?php

namespace App\Filament\Resources\ManualSections\Pages;

use App\Filament\Resources\ManualSections\ManualSectionResource;
use Filament\Resources\Pages\ListRecords;

class ListManualSections extends ListRecords
{
    protected static string $resource = ManualSectionResource::class;

    public function getSubheading(): ?string
    {
        return 'Order, nest and archive sections. New content is written in the portal and approved under Manual Approvals.';
    }
}
