<?php

namespace App\Filament\Resources\WorkTypes\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;

class WorkTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Counts as')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\WorkType ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state) => $state instanceof \App\Enums\WorkType ? $state->color() : 'gray'),
                TextColumn::make('color')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !\App\Support\Platform::showsOrganisations()),
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


