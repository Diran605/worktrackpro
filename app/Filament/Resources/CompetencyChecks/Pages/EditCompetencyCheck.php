<?php

namespace App\Filament\Resources\CompetencyChecks\Pages;

use App\Filament\Resources\CompetencyChecks\CompetencyCheckResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompetencyCheck extends EditRecord
{
    use SavesCompetencyCheck;

    protected static string $resource = CompetencyCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->cleanCheckData($data);
    }

    protected function afterSave(): void
    {
        $this->assignPlans($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
