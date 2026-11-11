<?php

namespace App\Filament\Resources\Letters\Schemas;

use App\Support\LetterPlaceholders;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LetterTemplateForm
{
    public const TYPES = [
        'appointment' => 'Appointment',
        'warning' => 'Warning',
        'query' => 'Query',
        'confirmation' => 'Confirmation',
        'custom' => 'Custom',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('About this template')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Template name')
                            ->placeholder('e.g. Offer Letter 2026')
                            ->helperText('Only admins see this name, when choosing a template.')
                            ->required()
                            ->maxLength(255),
                        Select::make('letter_type')
                            ->label('Type')
                            ->options(self::TYPES)
                            ->required()
                            ->helperText('Used to group and filter issued letters.'),
                        Toggle::make('requires_acknowledgement')
                            ->label('Employee must acknowledge receipt')
                            ->helperText('Shows an "Acknowledge" button in the employee\'s inbox. Issued Letters shows who hasn\'t acknowledged yet. Typical for warnings and queries.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Letter content')
                    ->description(LetterPlaceholders::helpText())
                    ->schema([
                        TextInput::make('subject_template')
                            ->label('Subject')
                            ->placeholder('e.g. Formal warning — {{worker_name}}')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('body_template')
                            ->label('Letter text')
                            ->placeholder('Dear {{worker_name}}, …')
                            ->required(),
                    ]),
            ]);
    }
}
