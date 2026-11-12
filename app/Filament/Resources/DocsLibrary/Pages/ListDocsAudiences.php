<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\DocsAudienceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocsAudiences extends ListRecords
{
    protected static string $resource = DocsAudienceResource::class;

    public function getSubheading(): ?string
    {
        return 'Groups of readers, such as Teachers or Finance. Mark chapters for an audience under Knowledge > Manual Structure, then choose which audiences each access code opens.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New audience')
                ->mutateDataUsing(fn (array $data) => $data + ['organisation_id' => auth()->user()->organisation_id]),
        ];
    }
}
