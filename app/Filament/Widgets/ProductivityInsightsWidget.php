<?php

namespace App\Filament\Widgets;

use App\Models\DailyPlan;
use App\Models\User;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductivityInsightsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = '⚡ Worker Productivity This Week';

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && $user->hasPermissionTo('view_team_stats');
    }

    public function table(Table $table): Table
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d');
        $user = auth()->user();

        return $table
            ->query(
                User::query()
                    ->where('organisation_id', $user->organisation_id)
                    ->where('is_active', true)
                    ->when(!$user->hasRole('super_admin') && $user->department_id, function ($q) use ($user) {
                        $q->where('department_id', $user->department_id);
                    })
                    ->withCount([
                        'dailyPlans as planned_count' => function ($q) use ($weekStart, $weekEnd) {
                            $q->whereBetween('date', [$weekStart, $weekEnd]);
                        },
                        'dailyPlans as completed_count' => function ($q) use ($weekStart, $weekEnd) {
                            $q->whereBetween('date', [$weekStart, $weekEnd])
                              ->where('status', 'done');
                        },
                    ])
                    ->withSum([
                        'activityLogs as total_minutes' => function ($q) use ($weekStart, $weekEnd) {
                            $q->whereBetween('date', [$weekStart, $weekEnd]);
                        },
                    ], 'duration_minutes')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Worker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Dept'),
                TextColumn::make('total_minutes')
                    ->label('Hours Tracked')
                    ->formatStateUsing(fn ($state) => $state ? round($state / 60, 1) . 'h' : '0h')
                    ->sortable(),
                TextColumn::make('planned_count')
                    ->label('Planned')
                    ->sortable(),
                TextColumn::make('completed_count')
                    ->label('Done')
                    ->sortable(),
                TextColumn::make('execution_rate')
                    ->label('Exec. Rate')
                    ->getStateUsing(function ($record) {
                        if ($record->planned_count > 0) {
                            $rate = round(($record->completed_count / $record->planned_count) * 100);
                            return $rate . '%';
                        }
                        return '—';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->planned_count == 0) return 'gray';
                        $rate = ($record->completed_count / $record->planned_count) * 100;
                        if ($rate >= 80) return 'success';
                        if ($rate >= 50) return 'warning';
                        return 'danger';
                    }),
            ])
            ->defaultSort('total_minutes', 'desc')
            ->paginated(false);
    }
}
