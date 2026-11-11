<?php

namespace App\Filament\Resources\TaskTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaskTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('work_type')->badge(),
                TextColumn::make('expected_duration_minutes')->label('Est.')->suffix(' mins')->sortable(),
                TextColumn::make('recurrence_type')->badge(),
                TextColumn::make('recurrence_day')->label('Day')->placeholder('—'),
                TextColumn::make('department.name')->label('Department')->placeholder('—'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                SelectFilter::make('recurrence_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'one_time' => 'One Time',
                    ]),
                SelectFilter::make('is_active')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
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

