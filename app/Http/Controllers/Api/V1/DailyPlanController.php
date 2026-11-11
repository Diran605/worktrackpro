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
            ->with(['assignedByUser:id,name', 'projectClient:id,name'])
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
        
        $plan->update(['status' => 'done']);

        if ($plan->assigned_by && $plan->assigned_by !== $plan->user_id) {
            $plan->assignedByUser?->notify(new \App\Notifications\TaskCompletedNotification($plan));
        } else if ($plan->user) {
            $admin = \App\Models\User::where('department_id', $plan->user->department_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->first();
            if ($admin) {
                $admin->notify(new \App\Notifications\TaskCompletedNotification($plan));
            }
        }

        return new DailyPlanResource($plan->fresh());
    }
}
