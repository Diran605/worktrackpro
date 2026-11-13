<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use App\Filament\Resources\ManualSections\ManualSectionResource;
use App\Filament\Resources\Systems\SystemResource;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\SystemMember;
use App\Support\Modules;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Knowledge Base module: manuals, approvals and upkeep.
 */
class KnowledgeOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Knowledge base';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && Modules::allows(Modules::KNOWLEDGE)
            && ($user->can(Permissions::crud('systems', 'view')) || ManualChangeRequestResource::canReview());
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $systemIds = System::query()
            ->where('organisation_id', $user->organisation_id)
            ->where('status', '!=', 'retired')
            ->when(!$user->hasRole('super_admin') && !$user->can(Permissions::crud('systems', 'view')),
                fn ($q) => $q->whereIn('id', $user->ownedSystemIds()))
            ->pluck('id');

        $sections = ManualSection::query()->whereIn('system_id', $systemIds)->published();
        $staleDays = (int) config('knowledge.stale_after_days');
        $stale = (clone $sections)->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays($staleDays)))->count();
        $waiting = ManualChangeRequestResource::canReview()
            ? ManualChangeRequestResource::getEloquentQuery()->where('status', ManualChangeRequest::STATUS_SUBMITTED)->count()
            : 0;
        $withoutOwner = System::query()->whereIn('id', $systemIds)
            ->whereDoesntHave('members', fn ($q) => $q->whereIn('role', [SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER]))
            ->count();

        return [
            Stat::make('Waiting for your approval', $waiting)
                ->description($waiting > 0 ? 'Manual changes to review' : 'Nothing waiting')
                ->icon('heroicon-o-check-badge')
                ->color($waiting > 0 ? 'warning' : 'success')
                ->url(ManualChangeRequestResource::canAccess() ? ManualChangeRequestResource::getUrl('index') : null),
            Stat::make('Systems', $systemIds->count())
                ->description($withoutOwner > 0 ? "{$withoutOwner} without an owner" : 'All have an owner')
                ->icon('heroicon-o-server-stack')
                ->color($withoutOwner > 0 ? 'danger' : 'primary')
                ->url(SystemResource::canAccess() ? SystemResource::getUrl('index') : null),
            Stat::make('Published sections', (clone $sections)->count())
                ->description('Across all manuals')
                ->icon('heroicon-o-book-open')
                ->color('gray'),
            Stat::make("Not checked for {$staleDays}+ days", $stale)
                ->description($stale > 0 ? 'Ask owners to mark them as still accurate' : 'All recently checked')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stale > 0 ? 'warning' : 'success')
                ->url(ManualSectionResource::canAccess() ? ManualSectionResource::getUrl('index') : null),
        ];
    }
}
