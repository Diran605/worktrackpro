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
                TextColumn::make('department.name')->label('Department')->placeholder('—'),
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
                TextColumn::make('manual_version')->label('Manual')->prefix('v'),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['members.user', 'department']))
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('criticality')->options(System::CRITICALITIES),
                SelectFilter::make('status')->options(System::STATUSES),
                SelectFilter::make('department_id')->relationship('department', 'name')->label('Department'),
            ])
            ->recordActions([
                Action::make('manual')
                    ->label('Manual')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (System $record) => ManualSectionResource::getUrl('index', ['filters' => ['system_id' => ['value' => $record->id]]])),
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
}
