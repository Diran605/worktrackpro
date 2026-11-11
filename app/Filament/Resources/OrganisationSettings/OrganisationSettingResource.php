<?php

namespace App\Filament\Resources\OrganisationSettings;

use App\Filament\Resources\OrganisationSettings\Pages\EditOrganisationSetting;
use App\Filament\Resources\OrganisationSettings\Pages\ListOrganisationSettings;
use App\Filament\Resources\OrganisationSettings\Schemas\OrganisationSettingForm;
use App\Filament\Resources\OrganisationSettings\Tables\OrganisationSettingsTable;
use App\Models\OrganisationSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganisationSettingResource extends Resource
{
    protected static ?string $model = OrganisationSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Organisation Settings';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') || (auth()->user()?->hasRole('admin') ?? false);
    }

    /** Settings are created automatically (one per organisation), never by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OrganisationSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationSettings::route('/'),
            'edit' => EditOrganisationSetting::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('organisation');
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('organisation_id', $user->organisation_id);
    }
}

