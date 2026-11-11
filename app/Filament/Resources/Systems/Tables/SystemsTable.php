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
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
