<?php

namespace App\Filament\Resources\OrganisationSettings;

use App\Filament\Resources\OrganisationSettings\Pages\CreateOrganisationSetting;
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
            'create' => CreateOrganisationSetting::route('/create'),
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

