<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('user.name')
                    ->disabled(),
                \Filament\Forms\Components\TextInput::make('action')
                    ->disabled(),
                \Filament\Forms\Components\TextInput::make('auditable_type')
                    ->disabled(),
                \Filament\Forms\Components\KeyValue::make('old_values')
                    ->keyLabel('Field')
                    ->valueLabel('Data')
                    ->disabled(),
                \Filament\Forms\Components\KeyValue::make('new_values')
                    ->keyLabel('Field')
                    ->valueLabel('Data')
                    ->disabled(),
            ]);
    }
}
