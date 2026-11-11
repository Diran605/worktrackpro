<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'task_name' => $this->task_name,
            'project_client' => $this->project_client,
            'work_type' => [
                'value' => $this->work_type->value,
                'label' => $this->work_type->label(),
                'color' => $this->work_type->color(),
            ],
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'output' => $this->output,
            'completion_type' => [
                'value' => $this->completion_type->value,
                'label' => $this->completion_type->label(),
                'color' => $this->completion_type->color(),
            ],
            'is_planned' => collect([
                 true => 'Yes',
                 false => 'No',
            ])->get($this->is_planned),
            'raw_is_planned' => $this->is_planned, // the real boolean
            'daily_plan_id' => $this->daily_plan_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            'daily_plan' => new DailyPlanResource($this->whenLoaded('dailyPlan')),
        ];
    }
}
