<?php

namespace App\Filament\Resources\Letters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LetterTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            \Filament\Schemas\Components\Section::make('General Information')
                ->description('Give your template a name and category.')
                ->schema([
                    Select::make('letter_type')
                        ->required()
                        ->options([
                            'appointment' => 'Appointment',
                            'warning' => 'Warning',
                            'query' => 'Query',
                            'confirmation' => 'Confirmation',
                            'custom' => 'Custom',
                        ]),
                    TextInput::make('name')
                        ->label('Template Name (e.g., Offer Letter 2026)')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_system_default')
                        ->disabled()
                        ->helperText('System defaults cannot be modified.'),
                ]),

            \Filament\Schemas\Components\Section::make('Content Design')
                ->description('Design the subject and body. You can type words like [Salary] as placeholders, then fill them out later when generating the letter.')
                ->schema([
                    TextInput::make('subject_template')
                        ->label('Email/Letter Subject Line')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('body_template')
                        ->label('Letter Content (HTML allowed)')
                        ->helperText('Type your standard letter here. You can use placeholders like ____ or [Date] to remind yourself to fill them in later.')
                        ->rows(20)
                        ->required(),
                ]),
        ]);
    }
}

