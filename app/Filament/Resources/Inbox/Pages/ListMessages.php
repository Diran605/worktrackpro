<?php

namespace App\Filament\Resources\Inbox\Pages;

use App\Filament\Resources\Inbox\MessageResource;
use App\Models\MessageRecipient;
use App\Models\User;
use App\Services\InboxService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    public function getTabs(): array
    {
        $userId = auth()->id();
        $unread = MessageResource::unreadCount();

        return [
            'inbox' => Tab::make('Inbox')
                ->icon('heroicon-o-inbox')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'recipients',
                    fn (Builder $r) => $r->where('recipient_id', $userId)
                )),
            'unread' => Tab::make('Unread')
                ->icon('heroicon-o-envelope')
                ->badge($unread ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'recipients',
                    fn (Builder $r) => $r->where('recipient_id', $userId)->whereNull('read_at')
                )),
            'sent' => Tab::make('Sent')
                ->icon('heroicon-o-paper-airplane')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('sender_id', $userId)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label('Mark all as read')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->visible(fn () => MessageResource::unreadCount() > 0)
                ->action(function () {
                    $count = MessageRecipient::query()
                        ->where('recipient_id', auth()->id())
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);

                    Notification::make()->title("{$count} message(s) marked as read")->success()->send();

                    // Reload so the sidebar badge updates
                    $this->redirect(MessageResource::getUrl('index', ['tab' => $this->activeTab]));
                }),

            Action::make('compose')
                ->label('Compose Message')
                ->icon('heroicon-o-pencil-square')
                ->modalHeading('Compose a new message')
                ->modalSubmitActionLabel('Send')
                ->schema([
                    Select::make('recipient_ids')
                        ->label('Recipients')
                        ->multiple()
                        ->searchable()
                        ->options(function () {
                            $user = auth()->user();

                            return User::query()
                                ->where('is_active', true)
                                ->where('id', '!=', $user->id)
                                ->when(!$user->hasRole('super_admin'), fn (Builder $query) => $query->where('organisation_id', $user->organisation_id))
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->required(),
                    TextInput::make('subject')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('body')
                        ->label('Message')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(InboxService::class)->sendMessage(
                        organisationId: (int) auth()->user()->organisation_id,
                        senderId: (int) auth()->id(),
                        recipientIds: array_map('intval', $data['recipient_ids']),
                        subject: $data['subject'],
                        body: $data['body'],
                        messageType: 'direct',
                    );

                    Notification::make()->title('Message sent')->success()->send();
                }),
        ];
    }
}
