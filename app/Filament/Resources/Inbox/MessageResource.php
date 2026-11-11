<?php

namespace App\Filament\Resources\Inbox;

use App\Filament\Resources\Inbox\Pages\ListMessages;
use App\Filament\Resources\Inbox\Tables\MessagesTable;
use App\Models\Message;
use App\Models\MessageRecipient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The signed-in user's own inbox: messages they received or sent. Nobody, including a super
 * admin, sees other people's messages here.
 */
class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static \UnitEnum|string|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Inbox';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(\App\Support\Permissions::VIEW_INBOX) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::unreadCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unread messages';
    }

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = auth()->id();

        return parent::getEloquentQuery()
            ->with(['sender:id,name', 'attachments'])
            ->where(fn (Builder $query) => $query
                ->whereHas('recipients', fn (Builder $r) => $r->where('recipient_id', $userId))
                ->orWhere('sender_id', $userId))
            ->addSelect([
                // When the signed-in user read it (null = unread, or not a recipient)
                'my_read_at' => MessageRecipient::query()
                    ->select('read_at')
                    ->whereColumn('message_id', 'messages.id')
                    ->where('recipient_id', $userId)
                    ->limit(1),
                // Whether the signed-in user is a recipient (vs. only the sender)
                'my_recipient_id' => MessageRecipient::query()
                    ->select('id')
                    ->whereColumn('message_id', 'messages.id')
                    ->where('recipient_id', $userId)
                    ->limit(1),
            ]);
    }

    public static function unreadCount(): int
    {
        $userId = auth()->id();

        return $userId
            ? MessageRecipient::query()->where('recipient_id', $userId)->whereNull('read_at')->count()
            : 0;
    }

    /**
     * Mark the signed-in user's copy of these messages as read or unread.
     *
     * @param array<int> $messageIds
     */
    public static function markForCurrentUser(array $messageIds, bool $read): int
    {
        return MessageRecipient::query()
            ->where('recipient_id', auth()->id())
            ->whereIn('message_id', $messageIds)
            ->update(['read_at' => $read ? now() : null]);
    }

    public static function isUnread(Message $message): bool
    {
        return $message->my_recipient_id !== null && $message->my_read_at === null;
    }
}
