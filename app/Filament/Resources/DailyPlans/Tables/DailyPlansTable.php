<?php

namespace App\Filament\Resources\DailyPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use App\Services\OrganisationSettingsService;

class DailyPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Worker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('task_name')
                    ->label('Task')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('projectClient.name')
                    ->label('Project/Client')
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'done' => 'success',
                        'pending' => 'warning',
                        'carried_over' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('carry_over_count')
                    ->label('Carry #')
                    ->badge()
                    ->color(function ($state, $record): string {
                        $user = auth()->user();
                        $orgId = (int) ($user?->organisation_id ?? $record->organisation_id);
                        $threshold = (int) app(OrganisationSettingsService::class)->forOrganisation($orgId)->carry_over_flag_threshold;
                        return ((int) $state) >= max($threshold, 1) ? 'danger' : 'gray';
                    })
                    ->toggleable(),
                TextColumn::make('expected_duration_minutes')
                    ->label('Est. Time')
                    ->suffix(' mins')
                    ->sortable(),
                TextColumn::make('assignedByUser.name')
                    ->label('Assigned By')
                    ->placeholder('Self'),
            ])
            ->defaultSort('date', 'desc')
            ->defaultGroup('user.name')
            ->filters([
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !\App\Support\Platform::showsOrganisations()),
                \Filament\Tables\Filters\Filter::make('carried_over_too_often')
                    ->label('Carried over too often')
                    ->toggle()
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                        $threshold = (int) app(OrganisationSettingsService::class)
                            ->forOrganisation((int) auth()->user()->organisation_id)
                            ->carry_over_flag_threshold;

                        return $query->where('carry_over_count', '>=', max($threshold, 1));
                    }),
                \Filament\Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '=', $date),
                            );
                    }),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'done' => 'Done',
                        'carried_over' => 'Carried Over',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('priority')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}


