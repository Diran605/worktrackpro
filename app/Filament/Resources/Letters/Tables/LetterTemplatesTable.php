<?php

namespace App\Filament\Resources\Letters\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LetterTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('letter_type')->badge()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('subject_template')->limit(40),
                IconColumn::make('is_system_default')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                SelectFilter::make('letter_type')->options([
                    'appointment' => 'Appointment',
                    'warning' => 'Warning',
                    'query' => 'Query',
                    'confirmation' => 'Confirmation',
                    'custom' => 'Custom',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn ($record) => !$record->is_system_default),
            ]);
    }
}

