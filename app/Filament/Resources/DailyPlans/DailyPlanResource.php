<?php

namespace App\Filament\Resources\DailyPlans;

use App\Filament\Resources\DailyPlans\Pages\CreateDailyPlan;
use App\Filament\Resources\DailyPlans\Pages\EditDailyPlan;
use App\Filament\Resources\DailyPlans\Pages\ListDailyPlans;
use App\Filament\Resources\DailyPlans\Schemas\DailyPlanForm;
use App\Filament\Resources\DailyPlans\Tables\DailyPlansTable;
use App\Models\DailyPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyPlanResource extends Resource
{
    protected static ?string $model = DailyPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Task Plans';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('assign_plans') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return DailyPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyPlans::route('/'),
            'create' => CreateDailyPlan::route('/create'),
            'edit' => EditDailyPlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            // Super admin sees all plans across all orgs
            return $query;
        } elseif ($user) {
            // Admin sees plans for their department's workers
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        return $query;
    }
}
