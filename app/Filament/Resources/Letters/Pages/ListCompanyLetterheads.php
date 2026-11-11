<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\CompanyLetterheadResource;
use Filament\Resources\Pages\ListRecords;

class ListCompanyLetterheads extends ListRecords
{
    protected static string $resource = CompanyLetterheadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->createAnother(false),
        ];
    }
}

