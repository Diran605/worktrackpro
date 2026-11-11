<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->required()
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                Select::make('department_id')
                    ->relationship('department', 'name')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin') && auth()->user()->hasRole('admin')),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('roles')
                    ->relationship('roles', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => 
                        auth()->user()->hasRole('super_admin') ? $query : $query->where('name', '!=', 'super_admin')
                    )
                    ->multiple()
                    ->preload(),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Toggle::make('is_active')
                    ->default(true)
                    ->required()
            ]);
    }
}
