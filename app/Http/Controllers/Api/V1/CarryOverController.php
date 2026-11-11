<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CarryOverController extends Controller
{
    public function getPendingCarryOvers(Request $request): JsonResponse
    {
        $user = $request->user();

        $plans = DailyPlan::query()
            ->where('user_id', $user->id)
            ->where('status', PlanStatus::Pending->value)
            ->whereDate('date', '<', now()->toDateString())
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function resolveCarryOver(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();

        if ($plan->user_id !== $user->id) {
            abort(403);
        }

        if ($plan->status !== PlanStatus::Pending) {
            throw ValidationException::withMessages([
                'plan' => 'Only pending plans can be resolved.',
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['carry_over', 'cancel', 'leave'])],
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
        ]);

        $decision = $validated['decision'];

        if ($decision === 'leave') {
            return response()->json(['data' => $plan->fresh()]);
        }

        if ($decision === 'cancel') {
            $plan->update(['status' => PlanStatus::Cancelled]);
            return response()->json(['data' => $plan->fresh()]);
        }

        // carry_over
        $newPriority = $validated['priority'] ?? ($plan->priority?->value ?? 'medium');

        $new = DailyPlan::create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'assigned_by' => $plan->assigned_by,
            'date' => now()->toDateString(),
            'task_name' => $plan->task_name,
            'project_client' => $plan->project_client,
            'project_client_id' => $plan->project_client_id,
            'priority' => $newPriority,
            'expected_duration_minutes' => (int) $plan->expected_duration_minutes,
            'notes' => $plan->notes,
            'status' => PlanStatus::Pending,
            'is_assigned' => (bool) $plan->is_assigned,
            'task_template_id' => $plan->task_template_id,
            'personal_recurring_task_id' => $plan->personal_recurring_task_id,
            'carried_from_plan_id' => $plan->id,
            'carry_over_count' => (int) $plan->carry_over_count + 1,
        ]);

        $plan->update(['status' => PlanStatus::CarriedOver]);

        return response()->json([
            'data' => [
                'original' => $plan->fresh(),
                'new' => $new,
            ],
        ], 201);
    }
}

