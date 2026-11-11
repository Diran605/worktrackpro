<?php

namespace App\Filament\Resources\Letters\Tables;

use App\Filament\Resources\Letters\Schemas\LetterTemplateForm;
use App\Models\GeneratedLetter;
use App\Services\LetterService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IssuedLettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('template:id,requires_acknowledgement'))
            ->columns([
                TextColumn::make('generated_at')->label('Issued')->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('worker.name')->label('Employee')->searchable(),
                TextColumn::make('letter_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => LetterTemplateForm::TYPES[$state] ?? ucfirst((string) $state)),
                TextColumn::make('subject')->limit(40)->searchable(),
                TextColumn::make('acknowledgement')
                    ->label('Acknowledgement')
                    ->badge()
                    ->state(fn (GeneratedLetter $record) => match (true) {
                        (bool) $record->acknowledged_at => 'Acknowledged',
                        (bool) $record->template?->requires_acknowledgement => 'Waiting',
                        default => 'Not required',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Acknowledged' => 'success',
                        'Waiting' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (GeneratedLetter $record) => $record->acknowledged_at?->format('M j, Y H:i')),
                TextColumn::make('generator.name')->label('Issued by')->placeholder('—'),
            ])
            ->defaultSort('generated_at', 'desc')
            ->filters([
                SelectFilter::make('organisation_id')
                    ->relationship('organisation', 'name')
                    ->label('Organisation')
                    ->hidden(fn () => !auth()->user()->hasRole('super_admin')),
                SelectFilter::make('letter_type')->label('Type')->options(LetterTemplateForm::TYPES),
                Filter::make('awaiting_acknowledgement')
                    ->label('Waiting for acknowledgement')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->whereNull('acknowledged_at')
                        ->whereHas('template', fn (Builder $t) => $t->where('requires_acknowledgement', true))),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (GeneratedLetter $record) => $record->pdf_path && Storage::exists($record->pdf_path))
                    ->action(fn (GeneratedLetter $record) => Storage::download(
                        $record->pdf_path,
                        Str::slug($record->subject ?: 'letter') . '.pdf'
                    )),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalDescription(fn (GeneratedLetter $record) => "Send this letter to {$record->worker?->name}'s inbox again?")
                    ->visible(fn (GeneratedLetter $record) => (bool) $record->worker)
                    ->action(function (GeneratedLetter $record) {
                        // Same delivery as the first send, linked to this letter so it can be acknowledged.
                        app(LetterService::class)->deliver($record, 'This letter has been resent to you. Please see the attached PDF.');

                        Notification::make()->title('Letter resent')->success()->send();
                    }),
            ]);
    }
}
