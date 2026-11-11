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
                    ->placeholder('e.g. Client calls, Training, Admin'),
                Select::make('category')
                    ->label('Counts as')
                    ->options(collect(\App\Enums\WorkType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->default('direct')
                    ->required()
                    ->helperText('Productivity stats group work by these three categories. Direct = core work, Indirect = supporting work, Growth = learning.'),
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
