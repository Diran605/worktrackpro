<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Http\Requests\StoreActivityLogRequest;
use App\Http\Requests\UpdateActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ActivityLog::where('user_id', $request->user()->id)
            ->with(['dailyPlan'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }
        
        if ($request->has('work_type')) {
            $query->where('work_type', $request->work_type);
        }

        return ActivityLogResource::collection($query->paginate(20));
    }

    public function store(StoreActivityLogRequest $request)
    {
        $log = $this->activityLogService->createLog($request->user(), $request->validated());

        return new ActivityLogResource($log->load('dailyPlan'));
    }

    public function show(ActivityLog $log)
    {
        $this->authorize('view', $log);
        return new ActivityLogResource($log->load('dailyPlan'));
    }

    public function update(UpdateActivityLogRequest $request, ActivityLog $log)
    {
        $log = $this->activityLogService->updateLog($log, $request->validated());

        return new ActivityLogResource($log->load('dailyPlan'));
    }

    public function destroy(ActivityLog $log)
    {
        $this->authorize('delete', $log);
        $log->delete();

        return response()->noContent();
    }
}
