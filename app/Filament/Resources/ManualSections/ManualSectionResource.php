<?php

namespace App\Filament\Resources\ManualSections;

use App\Filament\Resources\ManualSections\Pages\ListManualSections;
use App\Models\ManualSection;
use App\Models\System;
use App\Services\ManualService;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Owners organise a manual here: order, nesting, archiving and "still accurate" checks.
 * Content itself is written in the portal and arrives through Manual Approvals.
 */
class ManualSectionResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::KNOWLEDGE;
    }

    // canEdit()/canDelete() below are enforced on the pages too, not just used to hide buttons.
    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = ManualSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static \UnitEnum|string|null $navigationGroup = 'Knowledge';

    protected static ?string $navigationLabel = 'Manual Structure';

    protected static ?string $modelLabel = 'manual section';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->can(Permissions::APPROVE_KB) || $user->ownedSystemIds() !== []);
    }

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
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $query->whereHas('system', fn ($q) => $q->where('organisation_id', $user?->organisation_id));

        if (!$user?->can(Permissions::APPROVE_KB)) {
            $query->whereIn('system_id', $user?->ownedSystemIds() ?? []);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $staleDays = (int) config('knowledge.stale_after_days');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['system', 'currentRevision', 'verifiedBy', 'audiences']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('system_id')->orderBy('sort_key'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('system.name')->label('Product'),
                TextColumn::make('number')->label('#')->placeholder('—'),
                TextColumn::make('title')
                    ->searchable()
                    ->formatStateUsing(fn (string $state, ManualSection $r) => str_repeat('↳ ', (int) $r->depth) . $state),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === ManualSection::KIND_UPLOADED_PDF ? 'PDF' : 'Written')
                    ->color(fn ($state) => $state === ManualSection::KIND_UPLOADED_PDF ? 'info' : 'gray'),
                TextColumn::make('version')
                    ->label('Version')
                    ->state(fn (ManualSection $r) => 'v' . $r->currentVersion()),
                TextColumn::make('last_verified_at')
                    ->label('Last verified')
                    ->since()
                    ->placeholder('Never')
                    ->color(fn (ManualSection $r) => !$r->last_verified_at || $r->last_verified_at->lt(now()->subDays($staleDays)) ? 'danger' : null),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === ManualSection::STATUS_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('visibility')
                    ->label('Library')
                    ->visible(fn () => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY))
                    ->badge()
                    ->icon(fn ($state) => $state === ManualSection::VISIBILITY_RESTRICTED ? 'heroicon-m-lock-closed' : null)
                    ->formatStateUsing(fn ($state) => $state === ManualSection::VISIBILITY_RESTRICTED ? 'Restricted' : 'As the manual')
                    ->color(fn ($state) => $state === ManualSection::VISIBILITY_RESTRICTED ? 'warning' : 'gray')
                    ->tooltip('Restricted chapters (and everything under them) can only be read by staff and people whose access code includes restricted chapters.'),
                TextColumn::make('audiences.name')
                    ->label('For')
                    ->visible(fn () => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY))
                    ->badge()
                    ->color('info')
                    ->placeholder('Everyone')
                    ->tooltip('Chapters meant for audiences (and everything under them) can only be read in the library by staff and by access codes that open one of those audiences.'),
            ])
            ->filters([
                SelectFilter::make('system_id')
                    ->label('Product')
                    ->relationship('system', 'name', fn (Builder $query) => auth()->user()->hasRole('super_admin')
                        ? $query
                        : $query->where('organisation_id', auth()->user()->organisation_id)),
                SelectFilter::make('audiences')
                    ->label('For audience')
                    ->relationship('audiences', 'name', fn (Builder $query) => auth()->user()->hasRole('super_admin')
                        ? $query
                        : $query->where('docs_audiences.organisation_id', auth()->user()->organisation_id)),
                SelectFilter::make('visibility')->label('Library')->options([
                    ManualSection::VISIBILITY_INHERIT => 'As the manual',
                    ManualSection::VISIBILITY_RESTRICTED => 'Restricted',
                ]),
                SelectFilter::make('status')->options([
                    ManualSection::STATUS_PUBLISHED => 'Published',
                    ManualSection::STATUS_ARCHIVED => 'Archived',
                ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('moveUp')
                        ->label('Move up')
                        ->icon('heroicon-o-arrow-up')
                        ->action(fn (ManualSection $record) => self::run(fn () => app(ManualService::class)->moveUp($record))),
                    Action::make('moveDown')
                        ->label('Move down')
                        ->icon('heroicon-o-arrow-down')
                        ->action(fn (ManualSection $record) => self::run(fn () => app(ManualService::class)->moveDown($record))),
                    Action::make('moveTo')
                        ->label('Move under…')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema(fn (ManualSection $record) => [
                            Select::make('parent_id')
                                ->label('New parent')
                                ->placeholder('Top level (a chapter)')
                                ->options(self::parentOptions($record))
                                ->default($record->parent_id),
                        ])
                        ->action(fn (ManualSection $record, array $data) => self::run(
                            fn () => app(ManualService::class)->moveTo($record, $data['parent_id'] ? (int) $data['parent_id'] : null),
                            'Section moved'
                        )),
                    Action::make('verify')
                        ->label('Mark as still accurate')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn (ManualSection $record) => self::run(
                            fn () => app(ManualService::class)->markVerified($record, auth()->user()),
                            'Marked as verified'
                        )),
                    Action::make('audiences')
                        ->label('Who is it for…')
                        ->visible(fn () => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY))
                        ->icon('heroicon-o-user-group')
                        ->modalHeading('Who is this part of the manual for?')
                        ->modalDescription('In the documentation library, a chapter meant for audiences (and everything under it) can only be read by staff and by access codes that open one of them. On the product page it is listed under each audience. Leave empty for everyone.')
                        ->schema(fn (ManualSection $record) => [
                            Select::make('audience_ids')
                                ->label('Audiences')
                                ->multiple()
                                ->options(fn () => \App\Models\DocsAudience::query()
                                    ->where('organisation_id', $record->system->organisation_id)
                                    ->orderBy('position')->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->default($record->audiences->pluck('id')->all())
                                ->helperText('Add audiences under Documentation Library > Audiences.'),
                        ])
                        ->action(fn (ManualSection $record, array $data) => self::run(function () use ($record, $data) {
                            $allowed = \App\Models\DocsAudience::query()
                                ->where('organisation_id', $record->system->organisation_id)
                                ->whereIn('id', $data['audience_ids'] ?? [])
                                ->pluck('id');
                            $record->audiences()->sync($allowed);
                        }, 'Saved')),
                    Action::make('restrict')
                        ->label('Make restricted')
                        ->icon('heroicon-o-lock-closed')
                        ->visible(fn (ManualSection $record) => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY) && $record->visibility !== ManualSection::VISIBILITY_RESTRICTED)
                        ->requiresConfirmation()
                        ->modalDescription('In the documentation library, this section and its sub-sections will only be readable by staff and by people whose access code includes restricted chapters. Staff in the portal are not affected.')
                        ->action(fn (ManualSection $record) => self::run(
                            fn () => $record->update(['visibility' => ManualSection::VISIBILITY_RESTRICTED]),
                            'Section restricted'
                        )),
                    Action::make('unrestrict')
                        ->label('Remove restriction')
                        ->icon('heroicon-o-lock-open')
                        ->visible(fn (ManualSection $record) => \App\Support\Modules::allows(\App\Support\Modules::DOCS_LIBRARY) && $record->visibility === ManualSection::VISIBILITY_RESTRICTED)
                        ->action(fn (ManualSection $record) => self::run(
                            fn () => $record->update(['visibility' => ManualSection::VISIBILITY_INHERIT]),
                            'Restriction removed'
                        )),
                    Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('The section and its sub-sections are hidden from readers. Their history is kept and they can be restored.')
                        ->visible(fn (ManualSection $record) => $record->status === ManualSection::STATUS_PUBLISHED)
                        ->action(fn (ManualSection $record) => self::run(fn () => app(ManualService::class)->archive($record), 'Archived')),
                    Action::make('restore')
                        ->label('Restore')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->visible(fn (ManualSection $record) => $record->status === ManualSection::STATUS_ARCHIVED)
                        ->action(fn (ManualSection $record) => self::run(fn () => app(ManualService::class)->restore($record), 'Restored')),
                ])->visible(fn (ManualSection $record) => $record->system->canBeApprovedBy(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualSections::route('/'),
        ];
    }

    /**
     * Published sections of the same manual, excluding this section and everything under it.
     */
    private static function parentOptions(ManualSection $record): array
    {
        $excluded = $record->descendantIdsAndSelf();

        return ManualSection::query()
            ->where('system_id', $record->system_id)
            ->published()
            ->whereNotIn('id', $excluded)
            ->orderBy('sort_key')
            ->get()
            ->mapWithKeys(fn (ManualSection $s) => [$s->id => str_repeat('— ', (int) $s->depth) . "{$s->number} {$s->title}"])
            ->all();
    }

    private static function run(callable $callback, ?string $success = null): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        if ($success) {
            Notification::make()->title($success)->success()->send();
        }
    }
}
