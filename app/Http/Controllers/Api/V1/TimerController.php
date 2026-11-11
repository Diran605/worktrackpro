<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyPlanResource;
use App\Models\DailyPlan;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimerController extends Controller
{
    public function __construct(private readonly TimerService $timers)
    {
    }

    public function start(Request $request, DailyPlan $plan): JsonResponse
    {
        $this->authorizeOwner($request, $plan);

        return $this->respond($this->timers->start($plan, $request->user()));
    }

    public function pause(Request $request, DailyPlan $plan): JsonResponse
    {
        $this->authorizeOwner($request, $plan);

        return $this->respond($this->timers->pause($plan));
    }

    public function resume(Request $request, DailyPlan $plan): JsonResponse
    {
        $this->authorizeOwner($request, $plan);

        return $this->respond($this->timers->resume($plan, $request->user()));
    }

    public function stop(Request $request, DailyPlan $plan): JsonResponse
    {
        $this->authorizeOwner($request, $plan);

        return $this->respond($this->timers->stop($plan));
    }

    private function authorizeOwner(Request $request, DailyPlan $plan): void
    {
        $this->authorize('update', $plan);
    }

    private function respond(DailyPlan $plan): JsonResponse
    {
        return response()->json(['data' => new DailyPlanResource($plan->load('assignedByUser'))]);
    }
}
