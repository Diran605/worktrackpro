<?php

namespace App\Filament\Resources\WorkTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;

class WorkTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('organisation_id')
                    ->default(fn () => auth()->user()->organisation_id),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Direct, Indirect, Growth'),
                Select::make('color')
                    ->options([
                        'success' => '🟢 Green',
                        'warning' => '🟡 Yellow',
                        'info' => '🔵 Blue',
                        'danger' => '🔴 Red',
                        'gray' => '⚪ Gray',
                    ])
                    ->default('success')
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Active'),
            ]);
    }
}
