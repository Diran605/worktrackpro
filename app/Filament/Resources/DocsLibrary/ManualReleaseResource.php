<?php

namespace App\Filament\Resources\DocsLibrary;

use App\Filament\Resources\DocsLibrary\Pages\ListManualReleases;
use App\Models\ManualRelease;
use App\Support\DocsContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Versions of each manual published by its owners (published from Knowledge > Systems).
 */
class ManualReleaseResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::DOCS_LIBRARY;
    }

    use \App\Filament\Concerns\ScopesToManagedSystems;

    protected static ?string $model = ManualRelease::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Published Versions';

    protected static ?string $modelLabel = 'published version';

    protected static ?int $navigationSort = 6;

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
        return parent::getEloquentQuery()->whereIn('system_id', static::managedSystemIds());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['system.organisation', 'publisher:id,name'])->withCount('revisions'))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('system.name')->label('Product')->searchable(),
                TextColumn::make('version')->prefix('v')->weight('bold'),
                IconColumn::make('is_major')->label('Major')->boolean(),
                TextColumn::make('summary')->wrap()->limit(120),
                TextColumn::make('revisions_count')->label('Changes')->numeric(),
                TextColumn::make('publisher.name')->label('Published by')->placeholder('—'),
                TextColumn::make('published_at')->label('Published')->dateTime('j M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('system_id')
                    ->label('Product')
                    ->relationship('system', 'name', fn (Builder $query) => static::systemFilterQuery($query)),
            ])
            ->recordActions([
                Action::make('open')
                    ->label("What's changed")
                    ->icon('heroicon-o-globe-alt')
                    ->color('gray')
                    ->visible(fn (ManualRelease $record) => $record->system?->isInLibrary())
                    ->url(fn (ManualRelease $record) => DocsContext::for($record->system->organisation)->system($record->system, 'changes') . '#v' . $record->version, shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualReleases::route('/'),
        ];
    }
}
