<?php

namespace App\Filament\Resources\ProjectClients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;

class ProjectClientForm
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
                    ->placeholder('e.g. Acme Corp, Internal'),
                Textarea::make('description')
                    ->rows(2)
                    ->placeholder('Brief description of this project/client'),
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Active'),
            ]);
    }
}
