<?php

namespace App\Filament\Resources\Inbox\Tables;

use App\Filament\Resources\Inbox\MessageResource;
use App\Http\Controllers\InboxAttachmentController;
use App\Models\Message;
use App\Services\InboxService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class MessagesTable
{
    public const TYPE_LABELS = [
        'direct' => 'Message',
        'standard' => 'Message',
        'broadcast' => 'Broadcast',
        'system' => 'System notice',
        'letter' => 'Letter',
        'reopen_request' => 'Reopen request',
        'reopen_response' => 'Reopen response',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('unread')
                    ->label('')
                    ->state(fn (Message $record) => MessageResource::isUnread($record))
                    ->boolean()
                    ->trueIcon('heroicon-s-envelope')
                    ->falseIcon('heroicon-o-envelope-open')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(fn (Message $record) => match (true) {
                        $record->my_recipient_id === null => 'Sent by you',
                        MessageResource::isUnread($record) => 'Unread',
                        default => 'Read',
                    })
                    ->width('1%'),
                TextColumn::make('sender.name')
                    ->label('From')
                    ->placeholder('System')
                    ->weight(fn (Message $record) => MessageResource::isUnread($record) ? 'bold' : null),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(60)
                    ->weight(fn (Message $record) => MessageResource::isUnread($record) ? 'bold' : null)
                    ->description(fn (Message $record) => str($record->body)->squish()->limit(90)->toString()),
                TextColumn::make('message_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::TYPE_LABELS[$state] ?? ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn ($state) => match ($state) {
                        'letter' => 'warning',
                        'system', 'reopen_request', 'reopen_response' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('attachments_count')
                    ->label('')
                    ->state(fn (Message $record) => $record->attachments->count() ?: null)
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder(''),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // Clicking a row opens the message
            ->recordAction('open')
            ->filters([
                SelectFilter::make('message_type')
                    ->label('Type')
                    ->options(array_unique(self::TYPE_LABELS)),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-envelope-open')
                    ->modalHeading(fn (Message $record) => $record->subject)
                    ->modalContent(fn (Message $record) => view('filament.inbox.message', [
                        'message' => $record,
                        'attachments' => $record->attachments->map(fn ($a) => [
                            'name' => $a->file_name,
                            'size' => $a->file_size,
                            'url' => InboxAttachmentController::signedUrl($a),
                        ]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    // Opening a message marks it read
                    ->mountUsing(fn (Message $record) => MessageResource::markForCurrentUser([$record->id], true)),

                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Message $record) => $record->sender_id && $record->sender_id !== auth()->id())
                    ->modalHeading(fn (Message $record) => 'Reply to ' . ($record->sender?->name ?? 'sender'))
                    ->modalSubmitActionLabel('Send')
                    ->schema(fn (Message $record) => [
                        TextInput::make('subject')
                            ->default(str_starts_with($record->subject, 'Re: ') ? $record->subject : 'Re: ' . $record->subject)
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Message')
                            ->rows(8)
                            ->required(),
                    ])
                    ->action(function (Message $record, array $data) {
                        app(InboxService::class)->sendMessage(
                            organisationId: (int) $record->organisation_id,
                            senderId: (int) auth()->id(),
                            recipientIds: [(int) $record->sender_id],
                            subject: $data['subject'],
                            body: $data['body'],
                            messageType: 'direct',
                        );
                        MessageResource::markForCurrentUser([$record->id], true);

                        Notification::make()->title('Reply sent')->success()->send();
                    }),

                ActionGroup::make([
                    Action::make('markRead')
                        ->label('Mark as read')
                        ->icon('heroicon-o-envelope-open')
                        ->visible(fn (Message $record) => MessageResource::isUnread($record))
                        ->action(fn (Message $record, $livewire) => self::markAndRefresh([$record->id], true, $livewire)),
                    Action::make('markUnread')
                        ->label('Mark as unread')
                        ->icon('heroicon-o-envelope')
                        ->visible(fn (Message $record) => $record->my_recipient_id !== null && !MessageResource::isUnread($record))
                        ->action(fn (Message $record, $livewire) => self::markAndRefresh([$record->id], false, $livewire)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markSelectedRead')
                        ->label('Mark as read')
                        ->icon('heroicon-o-envelope-open')
                        ->action(fn (Collection $records, $livewire) => self::markAndRefresh($records->pluck('id')->all(), true, $livewire))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markSelectedUnread')
                        ->label('Mark as unread')
                        ->icon('heroicon-o-envelope')
                        ->action(fn (Collection $records, $livewire) => self::markAndRefresh($records->pluck('id')->all(), false, $livewire))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Mark messages, then reload the page so the sidebar's unread badge updates too.
     */
    public static function markAndRefresh(array $messageIds, bool $read, $livewire): void
    {
        $count = MessageResource::markForCurrentUser($messageIds, $read);

        Notification::make()
            ->title($count === 1 ? ($read ? 'Marked as read' : 'Marked as unread') : "{$count} messages marked as " . ($read ? 'read' : 'unread'))
            ->success()
            ->send();

        $livewire->redirect(MessageResource::getUrl('index', ['tab' => $livewire->activeTab ?? null]));
    }
}
