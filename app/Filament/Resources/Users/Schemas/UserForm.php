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
                    ->hidden(fn () => !\App\Support\Platform::showsOrganisations()),
                Select::make('department_id')
                    ->relationship(
                        'department',
                        'name',
                        // Only departments in the admin's own organisation (super admins see all).
                        fn (\Illuminate\Database\Eloquent\Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('organisation_id', auth()->user()->organisation_id)
                    )
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin') && auth()->user()->hasRole('admin')),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('roles')
                    ->relationship('roles', 'name', function (\Illuminate\Database\Eloquent\Builder $query) {
                        $user = auth()->user();

                        if ($user->hasRole('super_admin')) {
                            return $query;
                        }

                        // Admins may assign core roles (except super_admin) and their organisation's custom roles.
                        return $query
                            ->where('name', '!=', 'super_admin')
                            ->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $user->organisation_id));
                    })
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
