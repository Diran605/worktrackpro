<?php

namespace App\Filament\Resources\Letters\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyLetterheadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('company_name')->required()->maxLength(255),
            FileUpload::make('header_image_path')
                ->label('Header Image')
                ->disk('public')
                ->directory('letterheads')
                ->image()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!$state) return;
                    $path = public_path('storage/' . $state);
                    $info = @getimagesize($path);
                    $set('header_height_px', (int) ($info[1] ?? 0));
                }),
            FileUpload::make('footer_image_path')
                ->label('Footer Image')
                ->disk('public')
                ->directory('letterheads')
                ->image()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!$state) return;
                    $path = public_path('storage/' . $state);
                    $info = @getimagesize($path);
                    $set('footer_height_px', (int) ($info[1] ?? 0));
                }),
            TextInput::make('header_height_px')->numeric()->disabled(),
            TextInput::make('footer_height_px')->numeric()->disabled(),
            ColorPicker::make('accent_color')->default('#0d9488'),
            Toggle::make('is_active')->default(true),
        ]);
    }
}

