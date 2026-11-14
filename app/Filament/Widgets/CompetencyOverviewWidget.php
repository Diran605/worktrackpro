<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Readiness;
use App\Filament\Resources\CompetencyReviews\CompetencyReviewResource;
use App\Models\CompetencySubmission;
use App\Models\User;
use App\Services\ReadinessService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Competency & Readiness module: answers to review, and products that depend on too few people.
 */
class CompetencyOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 22;

    protected ?string $heading = 'Competency & readiness';

    public static function canView(): bool
    {
        return Readiness::canAccess();
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if (CompetencyReviewResource::canReview()) {
            $waiting = CompetencyReviewResource::getEloquentQuery()
                ->where('status', CompetencySubmission::STATUS_SUBMITTED)
                ->where('user_id', '!=', $user->id)
                ->count();

            $stats[] = Stat::make('Answers to review', $waiting)
                ->description($waiting > 0 ? 'Waiting for you' : 'All reviewed')
                ->color($waiting > 0 ? 'warning' : 'success')
                ->url(CompetencyReviewResource::getUrl('index'));
        }

        $service = app(ReadinessService::class);
        $people = User::query()->where('organisation_id', $user->organisation_id)->where('is_active', true)->with('roles')->get();
        $matrix = $service->matrix((int) $user->organisation_id, $people);
        $cells = collect($matrix['rows'])->flatMap(fn ($r) => array_values($r['cells']))
            ->filter(fn ($c) => $c['level'] !== ReadinessService::NOT_REQUIRED);
        $ready = $cells->where('level', ReadinessService::READY)->count();

        $stats[] = Stat::make('Ready', $cells->isEmpty() ? '—' : round($ready / $cells->count() * 100) . '%')
            ->description($cells->isEmpty() ? 'No checks required yet' : "{$ready} of {$cells->count()} person-product pairs")
            ->url(Readiness::getUrl());

        $risks = $service->coverageRisks((int) $user->organisation_id)->count();
        $stats[] = Stat::make('Products with < 2 ready people', $risks)
            ->description($risks > 0 ? 'Only one person (or nobody) can do the job' : 'Every product has cover')
            ->color($risks > 0 ? 'danger' : 'success')
            ->url(Readiness::getUrl());

        return $stats;
    }
}
