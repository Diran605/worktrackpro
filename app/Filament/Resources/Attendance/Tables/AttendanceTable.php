<?php

namespace App\Filament\Resources\Attendance\Tables;

use App\Enums\SessionStatus;
use App\Models\WorkSession;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Worker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.department.name')
                    ->label('Department')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('clock_in')
                    ->label('Clock In')
                    ->dateTime('H:i'),
                TextColumn::make('clock_out')
                    ->label('Clock Out')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('total_minutes')
                    ->label('Total')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) return '—';
                        $h = intdiv((int) $state, 60);
                        $m = ((int) $state) % 60;
                        return ($h > 0 ? "{$h}h " : '') . "{$m}m";
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match (($state?->value ?? $state)) {
                        SessionStatus::Active->value => 'warning',
                        SessionStatus::Closed->value => 'success',
                        SessionStatus::SystemClosed->value => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state?->label() ?? ucfirst(str_replace('_', ' ', (string) $state))),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organisation_id')
                    ->relationship('user.organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                \Filament\Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('date', $date)
                        );
                    }),
                SelectFilter::make('status')
                    ->options([
                        SessionStatus::Active->value => 'Active',
                        SessionStatus::Closed->value => 'Closed',
                        SessionStatus::SystemClosed->value => 'System Closed',
                    ]),
            ])
            ->recordActions([
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WorkSession $record) => auth()->user()?->hasPermissionTo('reopen_sessions') && $record->status !== SessionStatus::Active)
                    ->requiresConfirmation()
                    ->modalDescription(fn (WorkSession $record) => $record->date && $record->date->lt(now()->subDays(7))
                        ? 'This session is older than 7 days. Reopening it may affect weekly stats already calculated and cached.'
                        : null)
                    ->action(function (WorkSession $record) {
                        $record->update([
                            'status' => SessionStatus::Active,
                            'clock_out' => null,
                        ]);
                    }),
            ]);
    }
}

