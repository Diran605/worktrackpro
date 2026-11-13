<?php

namespace App\Filament\Widgets;

use App\Models\WorkSession;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * Attendance module: how many people clocked in on each of the last 7 days.
 */
class AttendanceWeekChart extends ChartWidget
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'People who clocked in, last 7 days';

    protected ?string $maxHeight = '220px';

    public static function canView(): bool
    {
        return AttendanceTodayWidget::canView();
    }

    protected function getData(): array
    {
        $today = Carbon::parse(auth()->user()->localToday());
        $staffIds = CoreOverviewWidget::staffQuery()->pluck('id');
        $days = collect(range(6, 0))->map(fn ($back) => $today->copy()->subDays($back));

        $counts = WorkSession::query()
            ->whereIn('user_id', $staffIds)
            // Date-only comparisons: session dates may be stored with a time part.
            ->whereDate('date', '>=', $days->first()->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->get(['user_id', 'date'])
            ->groupBy(fn ($s) => Carbon::parse($s->date)->toDateString())
            ->map(fn ($sessions) => $sessions->unique('user_id')->count());

        return [
            'datasets' => [[
                'label' => 'People',
                'data' => $days->map(fn ($d) => $counts[$d->toDateString()] ?? 0)->all(),
                'fill' => true,
            ]],
            'labels' => $days->map(fn ($d) => $d->format('D j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]];
    }
}
