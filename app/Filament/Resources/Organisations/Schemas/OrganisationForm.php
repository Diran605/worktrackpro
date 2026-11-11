<?php

namespace App\Filament\Resources\Organisations\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organisation Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),

                Section::make('Branding')
                    ->description('Customize colors and assets for this organisation')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primary Color')
                            ->default('#0d9488')
                            ->helperText('Used throughout the frontend and reports'),
                        ColorPicker::make('secondary_color')
                            ->label('Secondary Color')
                            ->default('#6366f1'),
                        FileUpload::make('logo')
                            ->label('Organisation Logo')
                            ->image()
                            ->directory('org-logos')
                            ->maxSize(2048)
                            ->helperText('Appears in the sidebar and reports. Max 2MB.'),
                        FileUpload::make('letterhead')
                            ->label('Report Letterhead')
                            ->image()
                            ->directory('org-letterheads')
                            ->maxSize(5120)
                            ->helperText('Used as the header in exported PDF reports. Max 5MB.'),
                    ]),
            ]);
    }
}
