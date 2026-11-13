<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use App\Filament\Resources\DocsLibrary\DocsAccessRequestResource;
use App\Filament\Resources\DocsLibrary\DocsFeedbackResource;
use App\Filament\Resources\Systems\SystemResource;
use App\Models\DocsAccessCode;
use App\Models\DocsAccessRequest;
use App\Models\DocsFeedback;
use App\Models\ManualSection;
use App\Models\ManualSectionRevision;
use App\Models\System;
use App\Support\DocsContext;
use App\Support\Modules;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Documentation Library module: what readers see and ask for.
 */
class LibraryOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    protected ?string $heading = 'Documentation library';

    public static function canView(): bool
    {
        return Modules::allows(Modules::DOCS_LIBRARY) && (auth()->user()?->can(Permissions::crud('systems', 'view')) ?? false);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $organisation = $user->organisation;
        $library = System::query()
            ->where('organisation_id', $user->organisation_id)
            ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED]);

        $public = (clone $library)->where('library_visibility', System::LIBRARY_PUBLIC)->count();
        $restricted = (clone $library)->where('library_visibility', System::LIBRARY_RESTRICTED)->count();

        $requests = DocsAccessRequest::query()->whereNull('handled_at')
            ->whereHas('system', fn ($q) => $q->where('organisation_id', $user->organisation_id))->count();

        $feedback = DocsFeedback::query()->where('updated_at', '>=', now()->subDays(30))
            ->whereHas('section', fn ($q) => $q->withTrashed()->whereHas('system', fn ($s) => $s->where('organisation_id', $user->organisation_id)));
        $feedbackCount = (clone $feedback)->count();
        $helpful = $feedbackCount > 0 ? (int) round((clone $feedback)->where('helpful', true)->count() / $feedbackCount * 100) : null;

        $waiting = ManualSectionRevision::query()
            ->whereNull('release_id')
            ->whereIn('manual_section_id', ManualSection::withTrashed()->whereIn('system_id', (clone $library)->select('id'))->select('id'))
            ->count();

        $codes = DocsAccessCode::query()->where('organisation_id', $user->organisation_id)->get()->filter->isUsable()->count();

        return [
            Stat::make('Products in the library', $public + $restricted)
                ->description("{$public} public · {$restricted} restricted")
                ->icon('heroicon-o-globe-alt')
                ->color('primary')
                ->url($organisation ? DocsContext::for($organisation)->home() : null, shouldOpenInNewTab: true),
            Stat::make('Changes waiting to be published', $waiting)
                ->description($waiting > 0 ? 'Approved, not in the library yet' : 'Library is up to date')
                ->icon('heroicon-o-rocket-launch')
                ->color($waiting > 0 ? 'warning' : 'success')
                ->url(SystemResource::canAccess() ? SystemResource::getUrl('index') : null),
            Stat::make('Access requests', $requests)
                ->description($requests > 0 ? 'Readers waiting for an answer' : 'None open')
                ->icon('heroicon-o-hand-raised')
                ->color($requests > 0 ? 'danger' : 'success')
                ->url(DocsAccessRequestResource::canAccess() ? DocsAccessRequestResource::getUrl('index') : null),
            Stat::make('Reader feedback (30 days)', $feedbackCount)
                ->description($helpful === null ? 'No answers yet' : "{$helpful}% found it helpful")
                ->icon('heroicon-o-hand-thumb-up')
                ->color($helpful === null ? 'gray' : ($helpful >= 70 ? 'success' : 'warning'))
                ->url(DocsFeedbackResource::canAccess() ? DocsFeedbackResource::getUrl('index') : null),
            Stat::make('Active access codes', $codes)
                ->description('Codes readers can use now')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->url(DocsAccessCodeResource::canAccess() ? DocsAccessCodeResource::getUrl('index') : null),
        ];
    }
}
