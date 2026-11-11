<?php

namespace App\Filament\Resources\ManualChanges\Pages;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use App\Models\ManualChangeRequest;
use App\Services\ManualService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewManualChangeRequest extends ViewRecord
{
    protected static string $resource = ManualChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve & publish')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->canReviewRecord())
                ->modalHeading('Approve and publish this change')
                ->schema(fn () => array_filter([
                    Toggle::make('is_major')
                        ->label('Major change')
                        ->helperText('Bumps the manual to the next major version (e.g. 1.4 → 2.0). Use it when the way the system works has changed.'),
                    $this->record->type === ManualChangeRequest::TYPE_IMPORT
                        ? CheckboxList::make('include')
                            ->label('Sections to publish')
                            ->options(collect($this->record->importSections())
                                ->mapWithKeys(fn ($s, $i) => [$i => str_repeat('— ', (int) $s['depth']) . $s['title']]))
                            ->default(array_keys($this->record->importSections()))
                            ->bulkToggleable()
                        : null,
                ]))
                ->action(function (array $data) {
                    $skip = [];
                    if ($this->record->type === ManualChangeRequest::TYPE_IMPORT) {
                        $included = array_map('intval', $data['include'] ?? []);
                        $skip = array_values(array_diff(array_keys($this->record->importSections()), $included));
                    }

                    app(ManualService::class)->approve($this->record, auth()->user(), (bool) ($data['is_major'] ?? false), $skip);

                    Notification::make()->title('Published')->body('The manual has been updated and the author notified.')->success()->send();
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
