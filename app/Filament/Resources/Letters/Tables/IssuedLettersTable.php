<?php

namespace App\Filament\Resources\Letters\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IssuedLettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('generated_at')->dateTime()->sortable(),
                TextColumn::make('worker.name')->label('Worker')->searchable(),
                TextColumn::make('letter_type')->badge(),
                TextColumn::make('subject')->limit(40),
                IconColumn::make('acknowledged_at')->label('Acknowledged')->boolean(fn ($record) => (bool) $record->acknowledged_at),
                TextColumn::make('generator.name')->label('Issued By')->placeholder('—'),
            ])
            ->defaultSort('generated_at', 'desc')
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
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if (!$record->worker) return;

                        // Same delivery as the first send, linked to this letter so it can be acknowledged.
                        app(\App\Services\LetterService::class)->deliver(
                            $record,
                            'This letter has been resent to you. Please see the attached PDF.'
                        );
                    }),
            ]);
    }
}

