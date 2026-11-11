<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalRecurringTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalRecurringTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = PersonalRecurringTask::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'work_type' => ['required', 'string', 'in:direct,indirect,growth'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'expected_duration_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'recurrence_type' => ['required', 'string', 'in:daily,weekly'],
            'recurrence_day' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['recurrence_type'] === 'weekly' && !array_key_exists('recurrence_day', $validated)) {
            $validated['recurrence_day'] = null;
        }

        $task = PersonalRecurringTask::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $task], 201);
    }

    public function update(Request $request, PersonalRecurringTask $recurringTask): JsonResponse
    {
        if ($recurringTask->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'work_type' => ['sometimes', 'string', 'in:direct,indirect,growth'],
            'priority' => ['sometimes', 'string', 'in:high,medium,low'],
            'expected_duration_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'recurrence_type' => ['sometimes', 'string', 'in:daily,weekly'],
            'recurrence_day' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recurringTask->update($validated);

        return response()->json(['data' => $recurringTask->fresh()]);
    }

    public function destroy(Request $request, PersonalRecurringTask $recurringTask): JsonResponse
    {
        if ($recurringTask->user_id !== $request->user()->id) {
            abort(403);
        }

        $recurringTask->delete();

        return response()->json([], 204);
    }
}

