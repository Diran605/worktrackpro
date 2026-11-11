<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Services\PlanService;
use App\Http\Requests\StoreDailyPlanRequest;
use App\Http\Requests\UpdateDailyPlanRequest;
use App\Http\Resources\DailyPlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DailyPlanController extends Controller
{
    public function __construct(private readonly PlanService $planService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DailyPlan::where('user_id', $request->user()->id)
            ->with(['assignedByUser:id,name', 'projectClient:id,name', 'system:id,name,slug'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }

        return DailyPlanResource::collection($query->paginate(20));
    }

    public function store(StoreDailyPlanRequest $request)
    {
        $plan = $this->planService->createPlan($request->user(), $request->validated());

        return new DailyPlanResource($plan);
    }

    public function show(DailyPlan $plan)
    {
        $this->authorize('view', $plan);
        return new DailyPlanResource($plan);
    }

    public function update(UpdateDailyPlanRequest $request, DailyPlan $plan)
    {
        $plan = $this->planService->updatePlan($plan, $request->validated());

        return new DailyPlanResource($plan);
    }

    public function destroy(DailyPlan $plan)
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return response()->noContent();
    }

    /**
     * Mark a plan as complete.
     */
    public function complete(DailyPlan $plan)
    {
        $this->authorize('update', $plan);

        // Log the time on a running/paused timer before the plan is closed.
        if (in_array($plan->timer_status, [\App\Enums\TimerStatus::Running, \App\Enums\TimerStatus::Paused], true)) {
            $plan = app(\App\Services\TimerService::class)->stop($plan);
        }

        $plan->update(['status' => 'done']);

        if ($plan->assigned_by && $plan->assigned_by !== $plan->user_id) {
            $plan->assignedByUser?->notify(new \App\Notifications\TaskCompletedNotification($plan));
        } elseif ($plan->user) {
            \App\Models\User::whereIn('id', $plan->user->departmentAdminIds())
                ->get()
                ->each(fn ($admin) => $admin->notify(new \App\Notifications\TaskCompletedNotification($plan)));
        }

        return new DailyPlanResource($plan->fresh());
    }
}
