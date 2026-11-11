<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use App\Models\DailyPlan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;



    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && $user->hasPermissionTo('view_team_stats');
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d');

        $planQuery = DailyPlan::where('organisation_id', $user->organisation_id)
            ->whereBetween('date', [$weekStart, $weekEnd]);

        if (!$user->hasRole('super_admin') && $user->department_id) {
            $planQuery->whereHas('user', fn ($q) => $q->where('department_id', $user->department_id));
        }

        $totalPlans = (clone $planQuery)->count();
        $pendingPlans = (clone $planQuery)->where('status', 'pending')->count();
        $donePlans = (clone $planQuery)->where('status', 'done')->count();
        $overduePlans = (clone $planQuery)->where('status', 'pending')
            ->where('date', '<', Carbon::today()->format('Y-m-d'))
            ->count();

        $avgRate = $totalPlans > 0 ? round(($donePlans / $totalPlans) * 100) : 0;

        return [
            Stat::make('Plans This Week', $totalPlans)
                ->description($donePlans . ' completed')
                ->color('primary')
                ->icon('heroicon-o-clipboard-document-list'),
            Stat::make('Pending Tasks', $pendingPlans)
                ->description($overduePlans > 0 ? $overduePlans . ' overdue!' : 'All on track')
                ->color($overduePlans > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock'),
            Stat::make('Avg Execution Rate', $avgRate . '%')
                ->description('Team completion rate')
                ->color($avgRate >= 70 ? 'success' : ($avgRate >= 40 ? 'warning' : 'danger'))
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    protected function getHeading(): ?string
    {
        return '📋 Weekly Task Summary';
    }
}
