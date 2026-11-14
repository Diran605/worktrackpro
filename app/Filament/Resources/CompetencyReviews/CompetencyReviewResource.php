<?php

namespace App\Filament\Resources\CompetencyReviews;

use App\Filament\Resources\CompetencyReviews\Pages\ListCompetencyReviews;
use App\Filament\Resources\CompetencyReviews\Pages\ViewCompetencyReview;
use App\Models\CompetencySubmission;
use App\Models\System;
use App\Support\Modules;
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
use Illuminate\Support\HtmlString;

/**
 * Answers to competency checks, reviewed by the product's owners, backup owners and reviewers
 * (or anyone with "Review answers").
 */
class CompetencyReviewResource extends Resource
{
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return Modules::COMPETENCY;
    }

    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = CompetencySubmission::class;

    protected static ?string $slug = 'competency-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Competency';

    protected static ?string $navigationLabel = 'Competency Reviews';

    protected static ?string $modelLabel = 'answer';

    protected static ?int $navigationSort = 2;

    public static function canReview(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(Permissions::REVIEW_COMPETENCY)
            || $user->competencyReviewSystemIds() !== []
        );
    }

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

    /** Products whose answers the signed-in person reviews, as a subquery. */
    public static function reviewedSystemIds(): Builder
    {
        $user = auth()->user();
        $query = System::query()->select('id');

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $query->where('organisation_id', $user?->organisation_id);

        if (!$user?->can(Permissions::REVIEW_COMPETENCY)) {
            $query->whereIn('id', $user?->competencyReviewSystemIds() ?? []);
        }

        return $query;
    }

    /** Drafts stay private to the person answering. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', '!=', CompetencySubmission::STATUS_DRAFT)
            ->whereHas('check', fn ($q) => $q->whereIn('system_id', self::reviewedSystemIds()));
    }

    public static function getNavigationBadge(): ?string
    {
        if (!self::canReview()) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('status', CompetencySubmission::STATUS_SUBMITTED)
            ->where('user_id', '!=', auth()->id())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['check.system:id,name', 'user:id,name', 'reviewer:id,name']))
            ->columns([
                TextColumn::make('user.name')->label('Person')->searchable(),
                TextColumn::make('check.title')
                    ->label('Check')
                    ->description(fn (CompetencySubmission $r) => $r->check?->system?->name)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state, CompetencySubmission $r) => $r->status === CompetencySubmission::STATUS_APPROVED && !$r->isValidPass()
                        ? ($r->needs_recheck ? 'Passed (manual changed since)' : 'Passed (expired)')
                        : (CompetencySubmission::STATUS_LABELS[$state] ?? $state))
                    ->color(fn ($state) => match ($state) {
                        CompetencySubmission::STATUS_SUBMITTED => 'warning',
                        CompetencySubmission::STATUS_APPROVED => 'success',
                        CompetencySubmission::STATUS_CHANGES_REQUESTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('times_submitted')
                    ->label('Attempt')
                    ->formatStateUsing(fn ($state) => match ((int) $state) { 1 => '1st', 2 => '2nd', 3 => '3rd', default => "{$state}th" }),
                TextColumn::make('submitted_at')->label('Submitted')->since()->sortable(),
                TextColumn::make('reviewer.name')->label('Reviewed by')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CompetencySubmission::STATUS_LABELS)->except(CompetencySubmission::STATUS_DRAFT))
                    ->default(CompetencySubmission::STATUS_SUBMITTED),
                SelectFilter::make('product')
                    ->label('Product')
                    ->options(fn () => System::query()->whereIn('id', self::reviewedSystemIds())->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $id) => $q->whereHas('check', fn ($c) => $c->where('system_id', $id))
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label(fn (CompetencySubmission $r) => $r->status === CompetencySubmission::STATUS_SUBMITTED ? 'Review' : 'View'),
            ])
            ->emptyStateHeading('Nothing to review')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('The check')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('Person'),
                        TextEntry::make('check.system.name')->label('Product'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => CompetencySubmission::STATUS_LABELS[$state] ?? $state),
                        TextEntry::make('check.title')->label('Check')->columnSpan(2),
                        TextEntry::make('submitted_at')->label('Submitted')->dateTime(),
                        TextEntry::make('check.scenario')->label('The situation')->columnSpanFull()
                            ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
                        TextEntry::make('criteria')
                            ->label('What a good answer includes')
                            ->columnSpanFull()
                            ->state(fn (CompetencySubmission $r) => new HtmlString('<ol style="list-style:decimal;padding-left:1.25rem">'
                                . collect($r->check->criteriaList())->map(fn ($c) => '<li>' . e($c) . '</li>')->join('')
                                . '</ol>')),
                    ]),

                Section::make('Their answer')
                    ->schema([
                        TextEntry::make('answer')
                            ->hiddenLabel()
                            ->placeholder('No written answer.')
                            ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
                        TextEntry::make('screenshots')
                            ->label('Screenshots')
                            ->visible(fn (CompetencySubmission $r) => $r->evidence()->exists())
                            ->state(fn (CompetencySubmission $r) => new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:.75rem">'
                                . $r->evidence->map(fn ($e) => '<a href="' . e($e->url()) . '" target="_blank" rel="noopener">'
                                    . '<img src="' . e($e->url()) . '" alt="' . e($e->original_name) . '" style="max-height:220px;max-width:100%;border:1px solid #e5e7eb;border-radius:.5rem"></a>')->join('')
                                . '</div>')),
                        TextEntry::make('video_links')
                            ->label('Videos')
                            ->visible(fn (CompetencySubmission $r) => !empty($r->video_links))
                            ->state(fn (CompetencySubmission $r) => new HtmlString(self::videos($r->video_links ?? []))),
                    ]),

                Section::make('Earlier reviews')
                    ->visible(fn (CompetencySubmission $r) => $r->reviews()->exists())
                    ->collapsible()
                    ->schema([
                        TextEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (CompetencySubmission $r) => new HtmlString('<ul style="display:grid;gap:.75rem">'
                                . $r->reviews->map(fn ($rev) => '<li><strong>' . e($rev->decision === CompetencySubmission::STATUS_APPROVED ? 'Passed' : 'Sent back')
                                    . '</strong> by ' . e($rev->reviewer?->name ?? 'someone') . ', ' . e($rev->created_at?->format('j M Y H:i'))
                                    . ' · ' . count($rev->criteria_met ?? []) . ' of ' . count($r->check->criteriaList()) . ' items met'
                                    . ($rev->comment ? '<br><span style="white-space:pre-line">' . e($rev->comment) . '</span>' : '') . '</li>')->join('')
                                . '</ul>')),
                    ]),
            ]);
    }

    /** Links, with a YouTube player where possible. */
    private static function videos(array $links): string
    {
        return collect($links)->map(function ($url) {
            $html = '<p><a class="underline" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a></p>';

            if (preg_match('~^https://(?:www\.)?(?:youtube\.com/watch\?(?:.*&)?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
                $html .= '<iframe src="https://www.youtube-nocookie.com/embed/' . e($m[1]) . '" style="width:100%;max-width:560px;aspect-ratio:16/9;border:0;border-radius:.5rem;margin-top:.5rem" allowfullscreen loading="lazy"></iframe>';
            }

            return $html;
        })->join('') . '<p style="font-size:.8rem;opacity:.7;margin-top:.5rem">If a video doesn\'t open, ask the person to share it with you.</p>';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetencyReviews::route('/'),
            'view' => ViewCompetencyReview::route('/{record}'),
        ];
    }
}
