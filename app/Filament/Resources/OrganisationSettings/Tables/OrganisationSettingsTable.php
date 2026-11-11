<?php

namespace App\Filament\Resources\OrganisationSettings\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganisationSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organisation.name')->label('Organisation')->searchable(),
                TextColumn::make('abandoned_timer_hours')->label('Timer Hrs')->sortable(),
                TextColumn::make('carry_over_flag_threshold')->label('Carry Threshold')->sortable(),
                TextColumn::make('inbox_max_attachment_kb')->label('Max KB')->sortable(),
                TextColumn::make('abandoned_session_close_time')->label('Close Time'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),
            ]);
    }
}

