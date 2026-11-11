<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->required()
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->organisation_id),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull()
            ]);
    }
}
