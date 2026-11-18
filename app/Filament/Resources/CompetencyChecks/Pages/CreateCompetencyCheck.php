<?php

namespace App\Filament\Resources\CompetencyChecks\Pages;

use App\Filament\Resources\CompetencyChecks\CompetencyCheckResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompetencyCheck extends CreateRecord
{
    use SavesCompetencyCheck;

    protected static string $resource = CompetencyCheckResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->cleanCheckData($data) + ['created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        $this->assignPlans($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
