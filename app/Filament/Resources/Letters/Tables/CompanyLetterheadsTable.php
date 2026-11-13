<?php

namespace App\Filament\Resources\Letters\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyLetterheadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Company')->searchable(),
                \Filament\Tables\Columns\ImageColumn::make('header_image_path')->label('Header')->disk('public')->height(32),
                \Filament\Tables\Columns\ColorColumn::make('accent_color')->label('Accent'),
                IconColumn::make('is_active')->label('In use')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !\App\Support\Platform::showsOrganisations()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

