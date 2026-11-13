<?php

namespace App\Filament\Widgets;

use App\Enums\SessionStatus;
use App\Filament\Pages\AttendanceOverview;
use App\Filament\Resources\Attendance\SessionReopenRequestResource;
use App\Models\SessionReopenRequest;
use App\Models\WorkSession;
use App\Support\Modules;
use App\Support\Permissions;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Attendance module: who is in today.
 */
class AttendanceTodayWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Attendance today';

    public static function canView(): bool
    {
        return Modules::allows(Modules::ATTENDANCE) && (auth()->user()?->can(Permissions::MANAGE_ATTENDANCE) ?? false);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = $user->localToday();
        $yesterday = Carbon::parse($today)->subDay()->toDateString();
        $staffIds = CoreOverviewWidget::staffQuery()->where('is_active', true)->pluck('id');

        $sessionsToday = WorkSession::query()->whereIn('user_id', $staffIds)->whereDate('date', $today);
        $clockedInNow = (clone $sessionsToday)->where('status', SessionStatus::Active->value)->count();
        $cameInToday = (clone $sessionsToday)->distinct('user_id')->count('user_id');
        $autoClosed = WorkSession::query()->whereIn('user_id', $staffIds)->whereDate('date', $yesterday)
            ->where('status', SessionStatus::SystemClosed->value)->count();
        $openRequests = SessionReopenRequest::query()->where('status', 'pending')
            ->whereIn('requested_by', $staffIds)->count();

        return [
            Stat::make('Clocked in now', $clockedInNow)
                ->description("of {$staffIds->count()} active staff")
                ->icon('heroicon-o-clock')
                ->color('success')
                ->url(AttendanceOverview::canAccess() ? AttendanceOverview::getUrl() : null),
            Stat::make('Not clocked in yet', max(0, $staffIds->count() - $cameInToday))
                ->description('Today')
                ->icon('heroicon-o-user-minus')
                ->color('gray'),
            Stat::make('Auto-closed overnight', $autoClosed)
                ->description('Forgot to clock out yesterday')
                ->icon('heroicon-o-moon')
                ->color($autoClosed > 0 ? 'warning' : 'gray'),
            Stat::make('Reopen requests', $openRequests)
                ->description($openRequests > 0 ? 'Waiting for a decision' : 'None waiting')
                ->icon('heroicon-o-arrow-path')
                ->color($openRequests > 0 ? 'danger' : 'success')
                ->url(SessionReopenRequestResource::canAccess() ? SessionReopenRequestResource::getUrl('index') : null),
        ];
    }
}
