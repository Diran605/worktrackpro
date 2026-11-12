<?php

namespace App\Filament\Resources\Systems\Tables;

use App\Filament\Resources\ManualSections\ManualSectionResource;
use App\Models\System;
use App\Models\SystemMember;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SystemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (System $record) => $record->summary),
                TextColumn::make('owners')
                    ->label('Owners')
                    ->state(fn (System $record) => $record->members
                        ->whereIn('role', [SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER])
                        ->map(fn ($m) => $m->user?->name)
                        ->filter()
                        ->join(', '))
                    ->placeholder('No owner'),
                TextColumn::make('department.name')->label('Department')->placeholder('â€”'),
                TextColumn::make('criticality')
                    ->badge()
                    ->formatStateUsing(fn ($state) => System::CRITICALITIES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => System::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('manual_version')
                    ->label('Manual')
                    ->prefix('v')
                    ->description(fn (System $record) => $record->unreleased_count > 0
                        ? $record->unreleased_count . ' ' . str('change')->plural($record->unreleased_count) . ' waiting for the library'
                        : null),
                TextColumn::make('library_visibility')
                    ->label('Library')
                    ->badge()
                    ->formatStateUsing(fn ($state) => System::LIBRARY_VISIBILITIES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        System::LIBRARY_PUBLIC => 'success',
                        System::LIBRARY_RESTRICTED => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (System $record) => $record->category?->name),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['members.user', 'department', 'category', 'organisation'])
                ->addSelect(['unreleased_count' => \App\Models\ManualSectionRevision::query()
                    ->selectRaw('count(*)')
                    ->whereNull('release_id')
                    ->whereIn('manual_section_id', \App\Models\ManualSection::withTrashed()->whereColumn('system_id', 'systems.id')->select('id')),
                ]))
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('criticality')->options(System::CRITICALITIES),
                SelectFilter::make('status')->options(System::STATUSES),
                SelectFilter::make('department_id')->relationship('department', 'name')->label('Department'),
                SelectFilter::make('library_visibility')->label('Library')->options(System::LIBRARY_VISIBILITIES),
            ])
            ->recordActions([
                Action::make('manual')
                    ->label('Manual')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (System $record) => ManualSectionResource::getUrl('index', ['filters' => ['system_id' => ['value' => $record->id]]])),
                Action::make('publishVersion')
                    ->label('Publish version')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn (System $record) => $record->unreleased_count > 0 && $record->canBeApprovedBy(auth()->user()))
                    ->modalHeading(fn (System $record) => "Publish a new version of the {$record->name} manual")
                    ->modalDescription(fn (System $record) => self::upcomingChangesHtml($record))
                    ->modalSubmitActionLabel('Publish version')
                    ->schema(fn (System $record) => [
                        \Filament\Forms\Components\Radio::make('is_major')
                            ->label('Kind of release')
                            ->options([
                                '0' => 'Minor (v' . $record->nextManualVersion(false) . '): corrections and small additions',
                                '1' => 'Major (v' . $record->nextManualVersion(true) . '): new chapters, or a process that now works differently',
                            ])
                            ->default('0')
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('summary')
                            ->label('What changed')
                            ->helperText("Shown to readers on the \"What's changed\" page of the library.")
                            ->required()
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (System $record, array $data) {
                        $release = app(\App\Services\ManualReleaseService::class)
                            ->publish($record, auth()->user(), (bool) $data['is_major'], $data['summary']);

                        \Filament\Notifications\Notification::make()
                            ->title("Version {$release->version} published")
                            ->success()
                            ->send();
                    }),
                Action::make('viewInLibrary')
                    ->label('View in library')
                    ->icon('heroicon-o-globe-alt')
                    ->color('gray')
                    ->visible(fn (System $record) => $record->isInLibrary())
                    ->url(fn (System $record) => \App\Support\DocsContext::for($record->organisation)->system($record), shouldOpenInNewTab: true),
                Action::make('downloadManual')
                    ->label('Download manual')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (System $record) {
                        try {
                            $export = app(\App\Services\ManualExportService::class)->request(
                                $record,
                                auth()->user(),
                                null,
                                \App\Models\ManualExport::APPENDICES,
                            );
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title(collect($e->errors())->flatten()->first())
                                ->warning()
                                ->send();

                            return null;
                        }

                        if ($export->isReady()) {
                            return redirect()->to($export->signedDownloadUrl());
                        }

                        \Filament\Notifications\Notification::make()
                            ->title($export->status === \App\Models\ManualExport::STATUS_FAILED ? 'The PDF could not be generated' : 'Generating the manual')
                            ->body($export->status === \App\Models\ManualExport::STATUS_FAILED
                                ? 'Please try again. If it keeps failing, check the application log.'
                                : 'This manual is large, so it is being generated in the background. You will get an inbox message when it is ready.')
                            ->color($export->status === \App\Models\ManualExport::STATUS_FAILED ? 'danger' : 'info')
                            ->send();

                        return null;
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * The approved changes a new version would contain.
     */
    private static function upcomingChangesHtml(System $record): \Illuminate\Support\HtmlString
    {
        $items = app(\App\Services\ManualReleaseService::class)->upcomingChanges($record)
            ->take(15)
            ->map(fn ($c) => '<li><strong>' . e($c['section']) . '</strong>' . ($c['summary'] ? ' — ' . e($c['summary']) : '') . '</li>')
            ->join('');

        $more = max(0, (int) $record->unreleased_count - 15);

        return new \Illuminate\Support\HtmlString(
            '<p>Changes approved since v' . e($record->manual_version) . ':</p>'
            . '<ul style="list-style:disc;padding-left:1.25rem;margin-top:.5rem">' . $items . '</ul>'
            . ($more > 0 ? "<p>…and {$more} more.</p>" : '')
        );
    }
}
