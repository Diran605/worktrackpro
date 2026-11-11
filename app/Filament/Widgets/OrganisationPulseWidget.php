<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrganisationPulseWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;



    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = app(StatsService::class)->getOrganisationWeeklyStats($user, Carbon::now()->startOfWeek());

        $totalHours = round(($stats['total_org_minutes'] ?? 0) / 60, 1);
        $direct = $stats['work_breakdown']['direct'] ?? 0;
        $indirect = $stats['work_breakdown']['indirect'] ?? 0;
        $growth = $stats['work_breakdown']['growth'] ?? 0;
        $totalMins = $direct + $indirect + $growth;
        $directPct = $totalMins > 0 ? round(($direct / $totalMins) * 100) : 0;

        return [
            Stat::make('Organisation Hours', $totalHours . 'h')
                ->description('This week across all teams')
                ->color('primary')
                ->icon('heroicon-o-building-office-2'),
            Stat::make('Active Personnel', $stats['total_active_personnel'] ?? 0)
                ->description('Across all departments')
                ->color('success')
                ->icon('heroicon-o-users'),
            Stat::make('Direct Output', $directPct . '%')
                ->description(round($direct / 60, 1) . 'h billable work')
                ->color($directPct >= 60 ? 'success' : ($directPct >= 40 ? 'warning' : 'danger'))
                ->icon('heroicon-o-arrow-trending-up'),
            Stat::make('Growth Investment', round($growth / 60, 1) . 'h')
                ->description($totalMins > 0 ? round(($growth / $totalMins) * 100) . '% of total' : '0%')
                ->color('info')
                ->icon('heroicon-o-academic-cap'),
        ];
    }

    protected function getHeading(): ?string
    {
        return '🏢 Organisation Pulse';
    }
}
