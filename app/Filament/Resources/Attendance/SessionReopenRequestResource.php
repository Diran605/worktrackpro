<?php

namespace App\Filament\Resources\Attendance;

use App\Models\SessionReopenRequest;
use App\Enums\SessionStatus;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class SessionReopenRequestResource extends Resource
{
    protected static ?string $model = SessionReopenRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Reopen Requests';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_attendance') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;

        $query = SessionReopenRequest::query()->where('status', 'pending');

        if (!$user->hasRole('super_admin')) {
            $query->whereHas('requestedBy', fn ($q) => $q->where('organisation_id', $user->organisation_id));
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestedBy.name')
                    ->label('Worker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('workSession.date')
                    ->label('Session Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->hidden(fn ($record) => $record->status !== 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('review_note')
                            ->label('Admin Note')
                            ->placeholder('Optional note for the worker'),
                    ])
                    ->action(function (SessionReopenRequest $record, array $data) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_note' => $data['review_note'] ?? null,
                        ]);

                        $record->workSession->update([
                            'status' => SessionStatus::Active,
                            'clock_out' => null,
                        ]);

                        // Notify worker
                        app(\App\Services\InboxService::class)->sendMessage(
                            organisationId: (int) $record->workSession->organisation_id,
                            senderId: auth()->id(),
                            recipientIds: [$record->requested_by],
                            subject: 'Session Reopen Approved',
                            body: "Your request to reopen the session for {$record->workSession->date->format('Y-m-d')} has been approved.\n\nNote: " . ($data['review_note'] ?? 'None'),
                            messageType: 'system'
                        );

                        Notification::make()
                            ->title('Request Approved')
                            ->body("The session has been reopened and worker notified.")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->hidden(fn ($record) => $record->status !== 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('review_note')
                            ->label('Reason for rejection')
                            ->required(),
                    ])
                    ->action(function (SessionReopenRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_note' => $data['review_note'],
                        ]);

                        // Notify worker
                        app(\App\Services\InboxService::class)->sendMessage(
                            organisationId: (int) $record->workSession->organisation_id,
                            senderId: auth()->id(),
                            recipientIds: [$record->requested_by],
                            subject: 'Session Reopen Rejected',
                            body: "Your request to reopen the session for {$record->workSession->date->format('Y-m-d')} was rejected.\n\nReason: " . $data['review_note'],
                            messageType: 'system'
                        );

                        Notification::make()
                            ->title('Request Rejected')
                            ->body("The request has been rejected and worker notified.")
                            ->danger()
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requestedBy', 'workSession']);

        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereHas('requestedBy', fn ($q) => $q->where('organisation_id', $user->organisation_id));
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Attendance\Pages\ListSessionReopenRequests::route('/'),
        ];
    }
}
