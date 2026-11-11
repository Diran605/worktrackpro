<?php

namespace App\Filament\Pages;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Filament\Pages\Page;

class AttendanceOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.attendance-overview';

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Attendance Overview';

    public string $date;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_attendance') ?? false;
    }

    public function getViewData(): array
    {
        $admin = auth()->user();
        $date = Carbon::parse($this->date)->toDateString();

        $usersQuery = User::query()
            ->where('is_active', true)
            ->where('organisation_id', $admin->organisation_id)
            ->with('department:id,name');

        if (!$admin->hasRole('super_admin')) {
            $usersQuery->where('department_id', $admin->department_id);
        }

        $users = $usersQuery->orderBy('name')->get();

        $sessions = WorkSession::query()
            ->whereDate('date', $date)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $rows = $users->map(function ($u) use ($sessions) {
            $s = $sessions->get($u->id);
            $status = $s?->status?->value ?? null;

            return [
                'user' => $u,
                'session' => $s,
                'status' => $status ?: 'absent',
            ];
        });

        return [
            'date' => $date,
            'rows' => $rows,
            'SessionStatus' => SessionStatus::class,
        ];
    }
}

