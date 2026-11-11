<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DepartmentOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;



    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('admin') && $user->department_id;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = app(StatsService::class)->getDepartmentWeeklyStats($user, Carbon::now()->startOfWeek());

        $totalHours = round(($stats['total_team_minutes'] ?? 0) / 60, 1);
        $direct = $stats['work_breakdown']['direct'] ?? 0;
        $indirect = $stats['work_breakdown']['indirect'] ?? 0;
        $growth = $stats['work_breakdown']['growth'] ?? 0;
        $totalMins = $direct + $indirect + $growth;

        return [
            Stat::make('Team Hours This Week', $totalHours . 'h')
                ->description($stats['active_members'] . ' active members')
                ->color('info')
                ->icon('heroicon-o-clock'),
            Stat::make('Direct Work', $totalMins > 0 ? round(($direct / $totalMins) * 100) . '%' : '0%')
                ->description(round($direct / 60, 1) . ' hours')
                ->color('success'),
            Stat::make('Indirect Work', $totalMins > 0 ? round(($indirect / $totalMins) * 100) . '%' : '0%')
                ->description(round($indirect / 60, 1) . ' hours')
                ->color('warning'),
            Stat::make('Growth Work', $totalMins > 0 ? round(($growth / $totalMins) * 100) . '%' : '0%')
                ->description(round($growth / 60, 1) . ' hours')
                ->color('info'),
        ];
    }

    protected function getHeading(): ?string
    {
        $dept = auth()->user()->department?->name ?? 'Department';
        return '📊 ' . $dept . ' Overview';
    }
}
