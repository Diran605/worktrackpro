<?php

namespace App\Filament\Resources\Letters;

use App\Filament\Resources\Letters\Pages\ListIssuedLetters;
use App\Filament\Resources\Letters\Tables\IssuedLettersTable;
use App\Models\GeneratedLetter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class IssuedLetterResource extends Resource
{
    protected static ?string $model = GeneratedLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static \UnitEnum|string|null $navigationGroup = 'Letters';

    protected static ?string $navigationLabel = 'Issued Letters';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_letters') ?? false;
    }

    public static function table(Table $table): Table
    {
        return IssuedLettersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssuedLetters::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['worker:id,name', 'generator:id,name']);
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('organisation_id', $user->organisation_id);
    }
}

