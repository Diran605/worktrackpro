<?php

namespace App\Filament\Resources\Letters;

use App\Filament\Resources\Letters\Pages\CreateCompanyLetterhead;
use App\Filament\Resources\Letters\Pages\EditCompanyLetterhead;
use App\Filament\Resources\Letters\Pages\ListCompanyLetterheads;
use App\Filament\Resources\Letters\Schemas\CompanyLetterheadForm;
use App\Filament\Resources\Letters\Tables\CompanyLetterheadsTable;
use App\Models\CompanyLetterhead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyLetterheadResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::LETTERS;
    }

    protected static ?string $model = CompanyLetterhead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Letters';

    protected static ?string $navigationLabel = 'Letterheads';

    public static function canViewAny(): bool
    {
        // Separate from manage_letters, so a custom role can issue letters without changing the letterhead.
        return auth()->user()?->can(\App\Support\Permissions::MANAGE_LETTERHEADS) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyLetterheadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyLetterheadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyLetterheads::route('/'),
            'create' => CreateCompanyLetterhead::route('/create'),
            'edit' => EditCompanyLetterhead::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('organisation_id', $user->organisation_id);
    }
}

