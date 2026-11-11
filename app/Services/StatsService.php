<?php

namespace App\Services;

use App\Models\User;
use App\Models\DailyPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsService
{
    /**
     * Get aggregate statistics for a specific user for a given period.
     * Uses raw DB queries to avoid Eloquent enum casting silently dropping rows.
     */
    public function getPersonalWeeklyStats(User $user, Carbon $startDateObj, ?Carbon $endDateObj = null): array
    {
        $endDateObj = $endDateObj ?? $startDateObj->copy()->endOfWeek();
        $startDate = $startDateObj->format('Y-m-d');
        $endDate = $endDateObj->format('Y-m-d');

        // Time spent by work_type — RAW query to bypass enum casts
        $timeByWorkType = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$startDate, $endDate])
            ->select('work_type', DB::raw('SUM(duration_minutes) as total_minutes'))
            ->groupBy('work_type')
            ->pluck('total_minutes', 'work_type')
            ->toArray();

        // Tasks Planned vs Completed
        $plannedCount = DailyPlan::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $completedPlannedCount = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_planned', true)
            ->where('completion_type', 'complete')
            ->whereNotNull('daily_plan_id')
            ->distinct('daily_plan_id')
            ->count('daily_plan_id');

        return [
            'total_minutes' => array_sum($timeByWorkType),
            'work_breakdown' => [
                'direct' => $timeByWorkType['direct'] ?? 0,
                'indirect' => $timeByWorkType['indirect'] ?? 0,
                'growth' => $timeByWorkType['growth'] ?? 0,
            ],
            'planner_stats' => [
                'total_planned' => $plannedCount,
                'completed_planned' => $completedPlannedCount,
                'execution_rate' => $plannedCount > 0 ? round(($completedPlannedCount / $plannedCount) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get aggregate statistics for a department (Admins).
     */
    public function getDepartmentWeeklyStats(User $admin, Carbon $startDateObj, ?Carbon $endDateObj = null): array
    {
        if (!$admin->department_id) {
            return [];
        }

        $endDateObj = $endDateObj ?? $startDateObj->copy()->endOfWeek();
        $startDate = $startDateObj->format('Y-m-d');
        $endDate = $endDateObj->format('Y-m-d');

        $userIds = User::where('department_id', $admin->department_id)
                       ->where('is_active', true)
                       ->pluck('id');

        $timeByWorkType = DB::table('activity_logs')
            ->whereIn('user_id', $userIds)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$startDate, $endDate])
            ->select('work_type', DB::raw('SUM(duration_minutes) as total_minutes'))
            ->groupBy('work_type')
            ->pluck('total_minutes', 'work_type')
            ->toArray();

        return [
            'total_team_minutes' => array_sum($timeByWorkType),
            'active_members' => $userIds->count(),
            'work_breakdown' => [
                'direct' => $timeByWorkType['direct'] ?? 0,
                'indirect' => $timeByWorkType['indirect'] ?? 0,
                'growth' => $timeByWorkType['growth'] ?? 0,
            ],
        ];
    }

    /**
     * Get full organisation statistics (Super Admins).
     */
    public function getOrganisationWeeklyStats(User $superAdmin, Carbon $startDateObj, ?Carbon $endDateObj = null): array
    {
        $endDateObj = $endDateObj ?? $startDateObj->copy()->endOfWeek();
        $startDate = $startDateObj->format('Y-m-d');
        $endDate = $endDateObj->format('Y-m-d');

        $totalUsers = User::where('organisation_id', $superAdmin->organisation_id)
                          ->where('is_active', true)
                          ->count();

        $timeByWorkType = DB::table('activity_logs')
            ->where('organisation_id', $superAdmin->organisation_id)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$startDate, $endDate])
            ->select('work_type', DB::raw('SUM(duration_minutes) as total_minutes'))
            ->groupBy('work_type')
            ->pluck('total_minutes', 'work_type')
            ->toArray();

        return [
            'total_org_minutes' => array_sum($timeByWorkType),
            'total_active_personnel' => $totalUsers,
            'work_breakdown' => [
                'direct' => $timeByWorkType['direct'] ?? 0,
                'indirect' => $timeByWorkType['indirect'] ?? 0,
                'growth' => $timeByWorkType['growth'] ?? 0,
            ],
        ];
    }

    /**
     * Get per-worker productivity statistics for PDF reports (Group J).
     */
    public function getWorkerProductivityReport(User $admin, Carbon $start, Carbon $end): array
    {
        $userIds = $admin->hasRole('super_admin') 
            ? User::where('organisation_id', $admin->organisation_id)->pluck('id')
            : User::where('department_id', $admin->department_id)->pluck('id');

        $users = User::with('department')->whereIn('id', $userIds)->orderBy('name')->get();
        $report = [];

        foreach ($users as $user) {
            $stats = $this->getPersonalWeeklyStats($user, $start);
            $report[] = [
                'user' => $user->name,
                'department' => $user->department?->name ?? 'None',
                'total_hours' => round($stats['total_minutes'] / 60, 2),
                'direct_pct' => $stats['total_minutes'] > 0 ? round(($stats['work_breakdown']['direct'] / $stats['total_minutes']) * 100) : 0,
                'execution_rate' => $stats['planner_stats']['execution_rate'],
                'total_planned' => $stats['planner_stats']['total_planned']
            ];
        }

        return $report;
    }
}
