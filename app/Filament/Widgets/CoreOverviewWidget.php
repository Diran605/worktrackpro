<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Inbox\MessageResource;
use App\Models\MessageRecipient;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Always on the dashboard, whatever modules the organisation has: staff and the inbox.
 */
class CoreOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Staff the signed-in admin looks after: their department if they have one (admins), otherwise
     * the whole organisation.
     */
    public static function staffQuery(): Builder
    {
        $user = auth()->user();

        return User::query()
            ->where('organisation_id', $user->organisation_id)
            ->when(!$user->hasRole('super_admin') && $user->department_id, fn ($q) => $q->where('department_id', $user->department_id));
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $active = static::staffQuery()->where('is_active', true)->count();
        $newThisMonth = static::staffQuery()->where('created_at', '>=', now()->startOfMonth())->count();
        $unread = MessageRecipient::query()->where('recipient_id', $user->id)->whereNull('read_at')->count();

        return [
            Stat::make('Active staff', $active)
                ->description($user->hasRole('super_admin') || !$user->department_id ? 'In the organisation' : 'In your department')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('New this month', $newThisMonth)
                ->description('People added since the 1st')
                ->icon('heroicon-o-user-plus')
                ->color('gray'),
            Stat::make('Unread messages', $unread)
                ->description($unread > 0 ? 'In your inbox' : 'Inbox is clear')
                ->icon('heroicon-o-inbox')
                ->color($unread > 0 ? 'warning' : 'success')
                ->url(MessageResource::canViewAny() ? MessageResource::getUrl('index') : null),
        ];
    }
}
