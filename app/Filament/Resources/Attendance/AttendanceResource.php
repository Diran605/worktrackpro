<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\Pages\ListAttendance;
use App\Filament\Resources\Attendance\Tables\AttendanceTable;
use App\Models\WorkSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = WorkSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Attendance';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_attendance') ?? false;
    }

    public static function table(Table $table): Table
    {
        return AttendanceTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendance::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user:id,name,department_id', 'user.department:id,name']);

        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        if ($user?->hasRole('admin')) {
            return $query->whereHas('user', fn (Builder $q) => $q->where('department_id', $user->department_id));
        }

        return $query->whereRaw('1 = 0');
    }
}

