<?php

namespace App\Filament\Resources\DocsAccessCodes\Pages;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use App\Models\DocsAccessCode;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDocsAccessCode extends CreateRecord
{
    protected static string $resource = DocsAccessCodeResource::class;

    protected static bool $canCreateAnother = false;

    private ?string $plainCode = null;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new DocsAccessCode($data);
        $record->organisation_id = auth()->user()->organisation_id;
        $record->created_by = auth()->id();
        $this->plainCode = $record->assignNewCode();
        $record->save();

        return $record;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Access code created: ' . $this->plainCode)
            ->body('Send this code to the reader. You can see it again on this page at any time.')
            ->success()
            ->persistent();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
