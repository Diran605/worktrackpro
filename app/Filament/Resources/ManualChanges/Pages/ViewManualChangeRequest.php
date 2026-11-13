<?php

namespace App\Filament\Resources\ManualChanges\Pages;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use App\Models\ManualChangeRequest;
use App\Services\ManualService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewManualChangeRequest extends ViewRecord
{
    protected static string $resource = ManualChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->canReviewRecord())
                ->modalHeading('Approve this change')
                ->modalDescription('Staff see the change in the portal straight away. Readers of the documentation library see it when the next version of the manual is published.')
                ->schema(fn () => array_filter([
                    $this->record->type === ManualChangeRequest::TYPE_IMPORT
                        ? CheckboxList::make('include')
                            ->label('Sections to publish')
                            ->options(collect($this->record->importSections())
                                ->mapWithKeys(fn ($s, $i) => [$i => str_repeat('— ', (int) $s['depth']) . $s['title']]))
                            ->default(array_keys($this->record->importSections()))
                            ->bulkToggleable()
                        : null,
                    \Filament\Forms\Components\Toggle::make('publish_now')
                        ->label('Publish to the library now as a minor version')
                        ->helperText(fn () => 'For urgent fixes. Releases this and every other approved change as v'
                            . $this->record->system->nextManualVersion(false) . '. Leave off to group it with other changes and publish a version later under Knowledge > Products.')
                        ->visible(fn () => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY) && $this->record->system->isInLibrary())
                        ->live(),
                    \Filament\Forms\Components\Textarea::make('release_summary')
                        ->label('What changed (shown to readers)')
                        ->placeholder('e.g. Corrected the support phone number')
                        ->rows(2)
                        ->maxLength(2000)
                        ->visible(fn ($get) => (bool) $get('publish_now'))
                        ->required(fn ($get) => (bool) $get('publish_now')),
                ]))
                ->action(function (array $data) {
                    $skip = [];
                    if ($this->record->type === ManualChangeRequest::TYPE_IMPORT) {
                        $included = array_map('intval', $data['include'] ?? []);
                        $skip = array_values(array_diff(array_keys($this->record->importSections()), $included));
                    }

                    app(ManualService::class)->approve($this->record, auth()->user(), $skip);

                    if (!empty($data['publish_now'])) {
                        $release = app(\App\Services\ManualReleaseService::class)
                            ->publish($this->record->system->fresh(), auth()->user(), false, $data['release_summary']);

                        Notification::make()
                            ->title("Approved and published as version {$release->version}")
                            ->body('Readers of the documentation library can see it now. The author has been notified.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Approved')
                            ->body('Staff can see it now. It reaches the documentation library with the next published version. The author has been notified.')
                            ->success()
                            ->send();
                    }
                    $this->backToQueue();
                }),

            Action::make('requestChanges')
                ->label('Send back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->canReviewRecord())
                ->schema([
                    Textarea::make('comment')
                        ->label('What needs to change?')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data) {
                    app(ManualService::class)->requestChanges($this->record, auth()->user(), $data['comment']);

                    Notification::make()->title('Sent back to the author')->success()->send();
                    $this->backToQueue();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->canReviewRecord())
                ->schema([
                    Textarea::make('comment')
                        ->label('Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    app(ManualService::class)->reject($this->record, auth()->user(), $data['comment']);

                    Notification::make()->title('Change rejected')->success()->send();
                    $this->backToQueue();
                }),
        ];
    }

    /**
     * Return to the pending list after a decision, so the page never shows an out-of-date status.
     */
    private function backToQueue(): void
    {
        $this->redirect(ManualChangeRequestResource::getUrl('index'));
    }

    private function canReviewRecord(): bool
    {
        return $this->record->status === ManualChangeRequest::STATUS_SUBMITTED
            && $this->record->system->canBeApprovedBy(auth()->user());
    }
}
