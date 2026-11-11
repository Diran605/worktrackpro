<?php

namespace App\Filament\Resources\TaskTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            Select::make('work_type')
                ->required()
                ->options([
                    'direct' => 'Direct',
                    'indirect' => 'Indirect',
                    'growth' => 'Growth / Training',
                ]),
            TextInput::make('expected_duration_minutes')
                ->required()
                ->numeric()
                ->minValue(0)
                ->maxValue(1440),
            Select::make('recurrence_type')
                ->required()
                ->options([
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'one_time' => 'One Time',
                ]),
            TextInput::make('recurrence_day')
                ->label('Recurrence Day (0=Sun … 6=Sat)')
                ->numeric()
                ->minValue(0)
                ->maxValue(6)
                ->visible(fn ($get) => $get('recurrence_type') === 'weekly'),
            Select::make('department_id')
                ->label('Department (optional)')
                ->relationship(
                    name: 'department',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        $user = auth()->user();
                        if ($user?->hasRole('super_admin')) {
                            return $query;
                        }
                        return $query->where('organisation_id', $user->organisation_id);
                    }
                )
                ->searchable()
                ->preload(),
            Toggle::make('assign_to_all')
                ->label('Assign to everyone in organisation')
                ->default(false)
                ->helperText('If enabled, this template will generate for all active workers in the organisation.'),
            Select::make('assignedUsers')
                ->label('Assign to specific workers (optional)')
                ->relationship(
                    name: 'assignedUsers',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        $user = auth()->user();
                        if ($user?->hasRole('super_admin')) {
                            return $query;
                        }
                        return $query->where('organisation_id', $user->organisation_id);
                    }
                )
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('If Department is set, it will be used; otherwise these assignments are used.'),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}

