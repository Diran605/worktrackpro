<?php

namespace App\Filament\Resources\OrganisationSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrganisationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('organisation_id')
                ->relationship(
                    name: 'organisation',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        $user = auth()->user();
                        if ($user?->hasRole('super_admin')) {
                            return $query;
                        }
                        return $query->where('id', $user->organisation_id);
                    }
                )
                ->required()
                ->disabled(fn () => !(auth()->user()?->hasRole('super_admin') ?? false)),
            TextInput::make('abandoned_timer_hours')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(24),
            TextInput::make('carry_over_flag_threshold')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(30),
            TextInput::make('inbox_max_attachment_kb')
                ->required()
                ->numeric()
                ->minValue(256)
                ->maxValue(51200),
            TagsInput::make('inbox_allowed_mime_types')
                ->label('Allowed MIME types (optional)')
                ->placeholder('e.g. application/pdf')
                ->helperText('Leave empty to allow any MIME type (not recommended).'),
            TextInput::make('abandoned_session_close_time')
                ->required()
                ->helperText('24h time, e.g. 20:00'),
        ]);
    }
}

