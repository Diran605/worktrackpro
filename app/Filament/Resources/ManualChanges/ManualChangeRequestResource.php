<?php

namespace App\Filament\Resources\ManualChanges;

use App\Filament\Resources\ManualChanges\Pages\ListManualChangeRequests;
use App\Filament\Resources\ManualChanges\Pages\ViewManualChangeRequest;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Support\ManualContent;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * Where system owners review manual changes submitted from the portal.
 */
class ManualChangeRequestResource extends Resource
{
    // canEdit()/canDelete() below are enforced on the pages too, not just used to hide buttons.
    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = ManualChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Knowledge';

    protected static ?string $navigationLabel = 'Manual Approvals';

    protected static ?string $modelLabel = 'manual change';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return self::canReview();
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

    public static function canReview(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(Permissions::APPROVE_KB)
            || $user->ownedSystemIds() !== []
        );
    }

    /**
     * Drafts stay private to their author. Owners see their systems; approve_kb sees the organisation.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('status', '!=', ManualChangeRequest::STATUS_DRAFT);
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

    public static function getNavigationBadge(): ?string
    {
        if (!self::canReview()) {
            return null;
        }

        $count = static::getEloquentQuery()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (ManualChangeRequest $r) => ManualChangeRequest::TYPE_LABELS[$r->type] ?? $r->type),
                TextColumn::make('system.name')->label('System')->sortable(),
                TextColumn::make('author.name')->label('Author'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ManualChangeRequest::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        ManualChangeRequest::STATUS_SUBMITTED => 'warning',
                        ManualChangeRequest::STATUS_APPROVED => 'success',
                        ManualChangeRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')->label('Submitted')->since()->sortable(),
                TextColumn::make('reviewer.name')->label('Reviewed by')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ManualChangeRequest::STATUS_LABELS)->except(ManualChangeRequest::STATUS_DRAFT))
                    ->default(ManualChangeRequest::STATUS_SUBMITTED),
                SelectFilter::make('system_id')
                    ->label('System')
                    ->relationship('system', 'name', fn (Builder $query) => auth()->user()->hasRole('super_admin')
                        ? $query
                        : $query->where('organisation_id', auth()->user()->organisation_id)),
            ])
            ->recordActions([
                ViewAction::make()->label('Review'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Change')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('system.name')->label('System'),
                        TextEntry::make('type')->formatStateUsing(fn ($state) => ManualChangeRequest::TYPE_LABELS[$state] ?? $state),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ManualChangeRequest::STATUS_LABELS[$state] ?? $state),
                        TextEntry::make('author.name')->label('Author'),
                        TextEntry::make('submitted_at')->label('Submitted')->dateTime(),
                        TextEntry::make('position')
                            ->label('Where it goes')
                            ->state(fn (ManualChangeRequest $r) => self::describePosition($r)),
                        TextEntry::make('change_summary')->label('Summary of the change')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('outdated')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->color('danger')
                            ->state('⚠ This section was changed after this edit was started. Compare carefully before approving.')
                            ->visible(fn (ManualChangeRequest $r) => $r->type === ManualChangeRequest::TYPE_EDIT
                                && $r->section
                                && $r->base_version !== null
                                && $r->section->currentVersion() !== $r->base_version),
                        TextEntry::make('review_comment')->label('Review comment')->placeholder('—')->columnSpanFull()
                            ->visible(fn (ManualChangeRequest $r) => filled($r->review_comment)),
                    ]),

                Section::make('Currently published')
                    ->description('What readers see today.')
                    ->visible(fn (ManualChangeRequest $r) => $r->type === ManualChangeRequest::TYPE_EDIT && $r->section)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('current_content')
                            ->hiddenLabel()
                            ->html()
                            ->prose()
                            ->state(fn (ManualChangeRequest $r) => self::renderSection($r->section?->currentRevision?->body, $r->section?->currentRevision?->file_path ? URL::temporarySignedRoute('kb.revision-file', now()->addHours(2), ['revision' => $r->section->current_revision_id]) : null, $r->section?->currentRevision?->title)),
                    ]),

                Section::make('Proposed')
                    ->visible(fn (ManualChangeRequest $r) => $r->type !== ManualChangeRequest::TYPE_IMPORT)
                    ->schema([
                        TextEntry::make('title')->label('Title'),
                        TextEntry::make('proposed_content')
                            ->hiddenLabel()
                            ->html()
                            ->prose()
                            ->state(fn (ManualChangeRequest $r) => self::renderSection(
                                $r->kind === ManualSection::KIND_WRITTEN ? $r->body : null,
                                $r->kind === ManualSection::KIND_UPLOADED_PDF && $r->file_path
                                    ? URL::temporarySignedRoute('kb.change-file', now()->addHours(2), ['change' => $r->id])
                                    : null,
                                $r->file_name,
                                $r->page_from,
                                $r->page_to,
                            )),
                    ]),

                Section::make('Sections in this Word import')
                    ->description('Untick sections you don\'t want when approving. Leaving out a section also leaves out its sub-sections. You can rename or reorder sections after approval under Manual Structure.')
                    ->visible(fn (ManualChangeRequest $r) => $r->type === ManualChangeRequest::TYPE_IMPORT)
                    ->schema([
                        TextEntry::make('import_outline')
                            ->hiddenLabel()
                            ->html()
                            ->state(fn (ManualChangeRequest $r) => self::importOutline($r)),
                        TextEntry::make('source_file')
                            ->label('Original file')
                            ->state(fn (ManualChangeRequest $r) => $r->file_path
                                ? new HtmlString('<a class="underline" href="' . e(URL::temporarySignedRoute('kb.change-file', now()->addHours(2), ['change' => $r->id])) . '">' . e($r->file_name) . '</a>')
                                : '—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualChangeRequests::route('/'),
            'view' => ViewManualChangeRequest::route('/{record}'),
        ];
    }

    public static function describePosition(ManualChangeRequest $r): string
    {
        if ($r->type === ManualChangeRequest::TYPE_EDIT) {
            return $r->section ? "Replaces {$r->section->number} {$r->section->title}" : 'Section no longer exists';
        }

        $parent = $r->proposedParent ? "under {$r->proposedParent->number} {$r->proposedParent->title}" : 'at the top level';
        $after = $r->proposedAfter ? ", after {$r->proposedAfter->number} {$r->proposedAfter->title}" : ', at the end';

        return ucfirst($parent) . $after;
    }

    private static function renderSection(?string $body, ?string $pdfUrl, ?string $fileName, ?int $from = null, ?int $to = null): HtmlString
    {
        if ($pdfUrl) {
            $range = $from || $to ? ' (pages ' . ($from ?: 1) . '–' . ($to ?: 'end') . ')' : '';

            return new HtmlString('<p>Uploaded PDF: <a href="' . e($pdfUrl) . '" target="_blank" rel="noopener">' . e($fileName ?: 'open file') . '</a>' . e($range) . '</p>');
        }

        $html = ManualContent::toDisplay($body);

        return new HtmlString($html !== '' ? $html : '<p><em>No content.</em></p>');
    }

    private static function importOutline(ManualChangeRequest $r): HtmlString
    {
        $items = collect($r->importSections())->map(fn ($s, $i) => '<li style="margin-left:' . ((int) $s['depth'] * 1.5) . 'rem">'
            . e(($i + 1) . '. ' . $s['title']) . '</li>');

        return new HtmlString('<ul>' . $items->join('') . '</ul>');
    }
}
