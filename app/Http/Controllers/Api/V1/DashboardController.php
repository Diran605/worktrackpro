<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use App\Support\Permissions;
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

        // Admins (team stats permission) get their department's numbers
        if ($user->can(Permissions::VIEW_TEAM_STATS)) {
            $response['department'] = $this->statsService->getDepartmentWeeklyStats($user, $weekStart);
        }

        // Super admins get the whole organisation
        if ($user->hasRole('super_admin')) {
            $response['organisation'] = $this->statsService->getOrganisationWeeklyStats($user, $weekStart);
        }

        return response()->json($response);
    }
}
