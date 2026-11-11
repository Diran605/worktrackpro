<?php

namespace App\Filament\Resources\Letters\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyLetterheadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Company details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company name')
                            ->helperText('Printed at the top of letters when there is no header image, and used for the {{company_name}} placeholder.')
                            ->required()
                            ->maxLength(255),
                        ColorPicker::make('accent_color')
                            ->label('Accent colour')
                            ->default('#0d9488')
                            ->helperText('Colour of the letter subject line (and the company name when there is no header image).'),
                    ]),

                Section::make('Header and footer images (optional)')
                    ->description('Wide banner images printed on every page. Without them, the company name is printed as text instead.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('header_image_path')
                            ->label('Header image')
                            ->disk('public')
                            ->directory('letterheads')
                            ->image()
                            ->maxSize(4096)
                            ->helperText('Shown across the top of each page, scaled to fit an area about 18 cm wide × 3 cm tall. Best: a wide PNG or JPG around 2000 × 300 px. Max 4 MB.'),
                        FileUpload::make('footer_image_path')
                            ->label('Footer image')
                            ->disk('public')
                            ->directory('letterheads')
                            ->image()
                            ->maxSize(4096)
                            ->helperText('Shown across the bottom of each page, in an area about 18 cm wide × 2.5 cm tall. Best: around 2000 × 250 px.'),
                    ]),

                Section::make()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Use this letterhead for new letters')
                            ->default(true)
                            ->helperText('Only one letterhead is in use at a time. Turning this on switches off your other letterheads. Letters already issued keep the letterhead they were generated with.'),
                    ]),
            ]);
    }
}
