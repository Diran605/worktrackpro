<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use App\Models\ManualChangeRequest;
use App\Support\Modules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Knowledge Base module: the manual changes waiting for the signed-in reviewer.
 */
class PendingManualApprovalsWidget extends TableWidget
{
    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Manual changes waiting for approval';

    public static function canView(): bool
    {
        return Modules::allows(Modules::KNOWLEDGE) && ManualChangeRequestResource::canReview();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ManualChangeRequestResource::getEloquentQuery()
                ->where('status', ManualChangeRequest::STATUS_SUBMITTED)
                ->with(['system:id,name', 'author:id,name']))
            ->defaultSort('updated_at')
            ->paginated([5])
            ->emptyStateHeading('Nothing waiting for approval')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (ManualChangeRequest $record) => ManualChangeRequestResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label('Change')->limit(60),
                TextColumn::make('system.name')->label('System'),
                TextColumn::make('author.name')->label('By'),
                TextColumn::make('updated_at')->label('Submitted')->since(),
            ]);
    }
}
