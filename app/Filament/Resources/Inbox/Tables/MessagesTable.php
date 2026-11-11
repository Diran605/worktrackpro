<?php

namespace App\Filament\Resources\Inbox\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('message_type')->badge(),
                TextColumn::make('sender.name')->label('Sender')->placeholder('System'),
                TextColumn::make('subject')->searchable()->limit(50),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

