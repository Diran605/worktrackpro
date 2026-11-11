<?php

namespace App\Filament\Resources\DailyPlans\Schemas;

use App\Models\ProjectClient;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DailyPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                Hidden::make('organisation_id')
                    ->default(fn () => $user->organisation_id),
                Hidden::make('assigned_by')
                    ->default(fn () => $user->id),

                Select::make('user_id')
                    ->label('Assign To')
                    ->options(function () use ($user) {
                        $query = User::where('organisation_id', $user->organisation_id)
                            ->where('is_active', true);

                        if (!$user->hasRole('super_admin') && $user->department_id) {
                            $query->where('department_id', $user->department_id);
                        }

                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('task_name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('What should this worker focus on?'),

                DatePicker::make('date')
                    ->required()
                    ->default(fn () => $user->localToday()),

                Select::make('project_client_id')
                    ->label('Project / Client')
                    ->options(function () use ($user) {
                        return ProjectClient::where('organisation_id', $user->organisation_id)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->placeholder('Select a project/client'),

                Select::make('system_id')
                    ->label('Related system (optional)')
                    ->helperText('For documentation or study tasks: the worker gets a link to this system\'s manual.')
                    ->options(fn () => \App\Models\System::query()
                        ->where('organisation_id', $user->organisation_id)
                        ->where('status', '!=', 'retired')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable(),

                TextInput::make('project_client')
                    ->label('Additional Details')
                    ->placeholder('Extra context about this project/client task')
                    ->helperText('Optional notes about the project scope'),

                Select::make('priority')
                    ->options([
                        'high' => '🔴 High',
                        'medium' => '🟡 Medium',
                        'low' => '🟢 Low',
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('work_type_id')
                    ->label('Work Type')
                    ->options(fn () => \App\Models\WorkType::query()
                        ->where('organisation_id', $user->organisation_id)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($t) => [$t->id => "{$t->name} ({$t->category->label()})"]))
                    ->required(),

                TextInput::make('expected_duration_minutes')
                    ->label('Expected Duration (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->default(60)
                    ->required()
                    ->suffix('mins'),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'done' => 'Done',
                        'carried_over' => 'Carried Over',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),

                Textarea::make('notes')
                    ->rows(2)
                    ->placeholder('Any instructions or context for the worker...'),
            ]);
    }
}
