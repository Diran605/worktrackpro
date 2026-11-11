<?php

namespace App\Filament\Resources\Letters;

use App\Filament\Resources\Letters\Pages\CreateLetterTemplate;
use App\Filament\Resources\Letters\Pages\EditLetterTemplate;
use App\Filament\Resources\Letters\Pages\ListLetterTemplates;
use App\Filament\Resources\Letters\Schemas\LetterTemplateForm;
use App\Filament\Resources\Letters\Tables\LetterTemplatesTable;
use App\Models\LetterTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LetterTemplateResource extends Resource
{
    protected static ?string $model = LetterTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static \UnitEnum|string|null $navigationGroup = 'Letters';

    protected static ?string $navigationLabel = 'Letter Templates';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_letters') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return LetterTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LetterTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLetterTemplates::route('/'),
            'create' => CreateLetterTemplate::route('/create'),
            'edit' => EditLetterTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('organisation_id')
              ->orWhere('organisation_id', $user->organisation_id);
        });
    }
}

