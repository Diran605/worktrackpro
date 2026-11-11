<?php

namespace App\Filament\Resources\Letters\Tables;

use App\Filament\Resources\Letters\LetterTemplateResource;
use App\Filament\Resources\Letters\Schemas\LetterTemplateForm;
use App\Models\LetterTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LetterTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LetterTemplate $record) => $record->is_system_default
                        ? 'System template (shared). Make a copy to customise it.'
                        : null),
                TextColumn::make('letter_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => LetterTemplateForm::TYPES[$state] ?? ucfirst($state))
                    ->sortable(),
                TextColumn::make('subject_template')->label('Subject')->limit(40),
                IconColumn::make('requires_acknowledgement')->label('Needs acknowledgement')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                SelectFilter::make('letter_type')->label('Type')->options(LetterTemplateForm::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Make a copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (LetterTemplate $record, $livewire) {
                        $copy = $record->replicate(['is_system_default', 'organisation_id', 'created_by', 'last_edited_by']);
                        $copy->fill([
                            'name' => $record->name . ' (copy)',
                            'organisation_id' => auth()->user()->organisation_id,
                            'is_system_default' => false,
                            'created_by' => auth()->id(),
                        ])->save();

                        $livewire->redirect(LetterTemplateResource::getUrl('edit', ['record' => $copy]));
                    }),
                DeleteAction::make(),
            ]);
    }
}
