<?php

namespace App\Filament\Resources\TaskTemplates;

use App\Filament\Resources\TaskTemplates\Pages\CreateTaskTemplate;
use App\Filament\Resources\TaskTemplates\Pages\EditTaskTemplate;
use App\Filament\Resources\TaskTemplates\Pages\ListTaskTemplates;
use App\Filament\Resources\TaskTemplates\Schemas\TaskTemplateForm;
use App\Filament\Resources\TaskTemplates\Tables\TaskTemplatesTable;
use App\Models\TaskTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskTemplateResource extends Resource
{
    // View / Add / Edit / Delete permissions ("task_templates.view" …), also enforced on the pages.
    use \App\Filament\Concerns\HasCrudPermissions;

    public static function permissionEntity(): string
    {
        return 'task_templates';
    }

    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::WORK_TRACKING;
    }

    protected static ?string $model = TaskTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static \UnitEnum|string|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'Recurring Task Templates';


    public static function form(Schema $schema): Schema
    {
        return TaskTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskTemplates::route('/'),
            'create' => CreateTaskTemplate::route('/create'),
            'edit' => EditTaskTemplate::route('/{record}/edit'),
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

