<?php

namespace App\Filament\Resources\DocsLibrary\Pages;

use App\Filament\Resources\DocsLibrary\SystemCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSystemCategories extends ListRecords
{
    protected static string $resource = SystemCategoryResource::class;

    public function getSubheading(): ?string
    {
        return 'Group products on the documentation library home page. Choose a product\'s category on its page under Knowledge > Products.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New category')
                ->mutateDataUsing(fn (array $data) => $data + ['organisation_id' => auth()->user()->organisation_id]),
        ];
    }
}
