<?php

namespace App\Filament\Pages;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\Permissions;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who clocked in on a given day. Admins see their department; super admins see the organisation.
 */
class AttendanceOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.attendance-overview';

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Attendance Overview';

    /** Session status => [label, badge colour]. "absent" = no session that day. */
    private const STATUSES = [
        'closed' => ['Present', 'success'],
        'active' => ['Clocked in', 'info'],
        'system_closed' => ['Auto-closed', 'warning'],
        'absent' => ['Absent', 'danger'],
    ];

    public static function canAccess(): bool
    {
        return \App\Support\Modules::allows(\App\Support\Modules::ATTENDANCE) && (auth()->user()?->can(Permissions::MANAGE_ATTENDANCE) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->workersWithSessions())
            ->heading(fn () => 'Attendance for ' . Carbon::parse($this->selectedDate())->format('l, j F Y'))
            ->description(fn () => $this->summary())
            ->columns([
                TextColumn::make('name')
                    ->label('Worker')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—'),
                TextColumn::make('session_clock_in')
                    ->label('Clock in')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('session_clock_out')
                    ->label('Clock out')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('hours')
                    ->label('Hours')
                    ->state(fn (User $record) => $this->hoursFor($record))
                    ->placeholder('—'),
                TextColumn::make('attendance_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record) => $record->session_status ?: 'absent')
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state][0] ?? ucfirst($state))
                    ->color(fn (string $state) => self::STATUSES[$state][1] ?? 'gray'),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Date')
                            ->default(fn () => auth()->user()->localToday())
                            ->maxDate(fn () => auth()->user()->localToday()),
                    ])
                    // The date is applied in workersWithSessions(); here it only labels the active filter.
                    ->query(fn (Builder $query) => $query)
                    ->indicateUsing(fn (array $data) => !empty($data['date']) && $data['date'] !== auth()->user()->localToday()
                        ? 'Date: ' . Carbon::parse($data['date'])->format('j M Y')
                        : null),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(self::STATUSES)->map(fn ($s) => $s[0])->all())
                    ->query(fn (Builder $query, array $data) => $this->filterByStatus($query, $data['value'] ?? null)),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name', fn (Builder $query) => $query->where('organisation_id', auth()->user()->organisation_id))
                    ->visible(fn () => \App\Support\Platform::showsOrganisations()),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->defaultSort('name')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('No workers to show')
            ->emptyStateDescription('There are no active workers in your scope, or none match the filters.');
    }

    public function selectedDate(): string
    {
        $date = $this->tableFilters['date']['date'] ?? null;

        return $date ? Carbon::parse($date)->toDateString() : auth()->user()->localToday();
    }

    /**
     * Active workers in scope, each with their session (if any) for the selected date.
     */
    private function workersWithSessions(): Builder
    {
        $admin = auth()->user();
        $date = $this->selectedDate();

        $session = fn (string $column) => WorkSession::query()
            ->select($column)
            ->whereColumn('work_sessions.user_id', 'users.id')
            ->whereDate('date', $date)
            ->limit(1);

        return $this->scopedWorkers()
            ->with('department:id,name')
            ->addSelect([
                'session_clock_in' => $session('clock_in'),
                'session_clock_out' => $session('clock_out'),
                'session_total_minutes' => $session('total_minutes'),
                'session_status' => $session('status'),
            ]);
    }

    private function scopedWorkers(): Builder
    {
        $admin = auth()->user();

        return User::query()
            ->where('is_active', true)
            ->where('organisation_id', $admin->organisation_id)
            ->when(!$admin->hasRole('super_admin'), fn (Builder $query) => $query->where('department_id', $admin->department_id));
    }

    private function filterByStatus(Builder $query, ?string $status): Builder
    {
        if (!$status) {
            return $query;
        }

        $date = $this->selectedDate();
        $sessions = fn (\Illuminate\Database\Query\Builder $q) => $q->from('work_sessions')
            ->whereColumn('work_sessions.user_id', 'users.id')
            ->whereDate('work_sessions.date', $date);

        return $status === 'absent'
            ? $query->whereNotExists(fn ($q) => $sessions($q))
            : $query->whereExists(fn ($q) => $sessions($q)->where('work_sessions.status', $status));
    }

    private function hoursFor(User $record): ?string
    {
        if ($record->session_total_minutes !== null) {
            return self::formatMinutes((int) $record->session_total_minutes);
        }

        // Still clocked in: show time so far
        if ($record->session_status === SessionStatus::Active->value && $record->session_clock_in) {
            return self::formatMinutes((int) Carbon::parse($record->session_clock_in)->diffInMinutes(now())) . ' so far';
        }

        return null;
    }

    private function summary(): string
    {
        $date = $this->selectedDate();
        $workerIds = $this->scopedWorkers()->pluck('id');

        $byStatus = WorkSession::query()
            ->whereIn('user_id', $workerIds)
            ->whereDate('date', $date)
            ->get(['status'])
            ->countBy(fn ($s) => $s->status->value);

        $present = $byStatus->sum();
        $absent = max(0, $workerIds->count() - $present);

        return collect([
            "{$workerIds->count()} workers",
            ($byStatus['closed'] ?? 0) . ' present',
            ($byStatus['active'] ?? 0) . ' still clocked in',
            ($byStatus['system_closed'] ?? 0) . ' auto-closed',
            "{$absent} absent",
        ])->join(' · ');
    }

    private static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
