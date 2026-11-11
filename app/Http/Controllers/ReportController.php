<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ExportService;
use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly StatsService $statsService
    ) {}

    public function workerPdf(Request $request, User $user)
    {
        // Must be authorized. We'll simplify and check if they belong to same org.
        if ($request->user()->organisation_id !== $user->organisation_id) {
            abort(403);
        }

        [$start, $end] = $this->parsePeriodDates($request);

        // Getting single user report as array
        $stats = $this->statsService->getPersonalWeeklyStats($user, $start, $end);
        $report[] = [
            'user' => $user->name,
            'department' => $user->department?->name ?? 'None',
            'total_hours' => round($stats['total_minutes'] / 60, 2),
            'direct_pct' => $stats['total_minutes'] > 0 ? round(($stats['work_breakdown']['direct'] / $stats['total_minutes']) * 100) : 0,
            'execution_rate' => $stats['planner_stats']['execution_rate'],
            'total_planned' => $stats['planner_stats']['total_planned']
        ];

        return $this->exportService->previewPdf('exports.team', [
            'report' => $report,
            'periodTitle' => $this->formatPeriodTitle($start, $end, $request->get('period', 'week'))
        ]);
    }

    public function teamPdf(Request $request)
    {
        $admin = $request->user();
        
        [$start, $end] = $this->parsePeriodDates($request);

        $report = $this->statsService->getWorkerProductivityReport(
            $admin, 
            $start, 
            $end
        );

        return $this->exportService->previewPdf('exports.team', [
            'report' => $report,
            'periodTitle' => $this->formatPeriodTitle($start, $end, $request->get('period', 'week'))
        ]);
    }

    private function formatPeriodTitle(Carbon $start, Carbon $end, string $period): string
    {
        return match ($period) {
            'day' => "Target Day: " . $start->format('F j, Y'),
            'month' => "Month of " . $start->format('F Y'),
            'year' => "Year " . $start->format('Y'),
            default => $start->format('M j') . " - " . $end->format('M j, Y'),
        };
    }

    private function parsePeriodDates(Request $request): array
    {
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::now();
        $period = $request->get('period', 'week');

        $start = $date->copy();
        $end = $date->copy();

        match ($period) {
            'day' => [$start->startOfDay(), $end->endOfDay()],
            'month' => [$start->startOfMonth(), $end->endOfMonth()],
            'year' => [$start->startOfYear(), $end->endOfYear()],
            default => [$start->startOfWeek(), $end->endOfWeek()],
        };

        return [$start, $end];
    }
}
