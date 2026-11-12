<?php

namespace App\Filament\Resources\DocsAccessCodes\Pages;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocsAccessCode extends EditRecord
{
    protected static string $resource = DocsAccessCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('New code')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('The current code stops working straight away, including in browsers where it was already entered. Send the new code to the reader.')
                ->action(function () {
                    DocsAccessCodeResource::regenerate($this->getRecord());
                    // "code" is a hidden attribute, so set the read-only field directly.
                    $this->data['code'] = $this->getRecord()->code;
                }),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
