<?php

namespace App\Filament\Resources\DocsLibrary;

use App\Filament\Resources\DocsLibrary\Pages\ListDocsFeedback;
use App\Models\DocsFeedback;
use App\Support\DocsContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Was this helpful?" answers left on manual sections in the documentation library.
 */
class DocsFeedbackResource extends Resource
{
    use \App\Filament\Concerns\ScopesToManagedSystems;

    protected static ?string $model = DocsFeedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Reader Feedback';

    protected static ?string $modelLabel = 'feedback';

    protected static ?string $pluralModelLabel = 'feedback';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('section', fn (Builder $query) => $query->withTrashed()->whereIn('system_id', static::managedSystemIds()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['section' => fn ($q) => $q->withTrashed()->with('system.organisation')]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('section.system.name')->label('Manual'),
                TextColumn::make('section.title')
                    ->label('Section')
                    ->description(fn (DocsFeedback $record) => $record->section?->number),
                IconColumn::make('helpful')
                    ->label('Helpful')
                    ->boolean()
                    ->trueIcon('heroicon-o-hand-thumb-up')
                    ->falseIcon('heroicon-o-hand-thumb-down'),
                TextColumn::make('comment')->wrap()->limit(200)->placeholder('—'),
                TextColumn::make('updated_at')->label('When')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('helpful')->label('Helpful'),
                Filter::make('with_comment')
                    ->label('With a comment')
                    ->query(fn (Builder $query) => $query->whereNotNull('comment')->where('comment', '!=', '')),
                SelectFilter::make('system')
                    ->label('Manual')
                    ->options(fn () => static::systemFilterQuery(\App\Models\System::query())->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereHas('section', fn ($q) => $q->withTrashed()->where('system_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open section')
                    ->icon('heroicon-o-globe-alt')
                    ->color('gray')
                    ->visible(fn (DocsFeedback $record) => $record->section && !$record->section->trashed() && $record->section->system?->isInLibrary())
                    ->url(fn (DocsFeedback $record) => DocsContext::for($record->section->system->organisation)->section($record->section->system, $record->section), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocsFeedback::route('/'),
        ];
    }
}
