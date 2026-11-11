<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly StatsService $statsService)
    {
    }

    public function weeklyStats(Request $request)
    {
        $user = $request->user();
        
        $weekStartInput = $request->query('week_start');
        $weekStart = $weekStartInput ? Carbon::parse($weekStartInput)->startOfWeek() : Carbon::now()->startOfWeek();

        $response = [
            'week_label' => $weekStart->format('M j') . ' - ' . $weekStart->copy()->endOfWeek()->format('M j, Y'),
            'personal' => $this->statsService->getPersonalWeeklyStats($user, $weekStart),
        ];

        // If the user has Admin rights to view team data, inject department stats
        if ($user->can('view-team-data')) {
            $response['department'] = $this->statsService->getDepartmentWeeklyStats($user, $weekStart);
        }

        // If the user has Super Admin rights to view all data, inject organisation stats
        if ($user->can('view-all-data')) {
            $response['organisation'] = $this->statsService->getOrganisationWeeklyStats($user, $weekStart);
        }

        return response()->json($response);
    }
}
