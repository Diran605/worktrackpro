<?php

namespace App\Filament\Resources\DocsLibrary;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use App\Filament\Resources\DocsLibrary\Pages\ListDocsAccessRequests;
use App\Models\DocsAccessRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Readers asking for access to restricted manuals from the documentation library.
 */
class DocsAccessRequestResource extends Resource
{
    use \App\Filament\Concerns\ScopesToManagedSystems;

    protected static ?string $model = DocsAccessRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Access Requests';

    protected static ?string $modelLabel = 'access request';

    protected static ?int $navigationSort = 4;

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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNull('handled_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['system:id,name', 'handler:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (DocsAccessRequest $record) => $record->email),
                TextColumn::make('system.name')->label('Manual'),
                TextColumn::make('reason')->wrap()->limit(150)->placeholder('—'),
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('handled_at')
                    ->label('Handled')
                    ->state(fn (DocsAccessRequest $record) => $record->handled_at ? 'Yes' : 'Open')
                    ->badge()
                    ->color(fn ($state) => $state === 'Open' ? 'warning' : 'success')
                    ->description(fn (DocsAccessRequest $record) => $record->handler?->name),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->label('Handled')
                    ->nullable()
                    ->attribute('handled_at')
                    ->default(false),
                SelectFilter::make('system_id')
                    ->label('Manual')
                    ->relationship('system', 'name', fn (Builder $query) => static::systemFilterQuery($query)),
            ])
            ->recordActions([
                Action::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->url(fn (DocsAccessRequest $record) => 'mailto:' . rawurlencode($record->email)),
                Action::make('createCode')
                    ->label('Create code')
                    ->icon('heroicon-o-key')
                    ->visible(fn () => DocsAccessCodeResource::canCreate())
                    ->url(fn () => DocsAccessCodeResource::getUrl('create')),
                Action::make('handled')
                    ->label('Mark handled')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (DocsAccessRequest $record) => !$record->handled_at)
                    ->action(fn (DocsAccessRequest $record) => $record->update(['handled_at' => now(), 'handled_by' => auth()->id()])),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (DocsAccessRequest $record) => (bool) $record->handled_at)
                    ->action(fn (DocsAccessRequest $record) => $record->update(['handled_at' => null, 'handled_by' => null])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocsAccessRequests::route('/'),
        ];
    }
}
