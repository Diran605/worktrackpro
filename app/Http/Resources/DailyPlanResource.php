<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'task_name' => $this->task_name,
            'project_client' => $this->project_client,
            'timer' => [
                'status' => $this->timer_status?->value ?? $this->timer_status,
                'started_at' => $this->timer_started_at?->toIso8601String(),
                'accumulated_seconds' => (int) ($this->timer_accumulated_seconds ?? 0),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
            ],
            'expected_duration_minutes' => $this->expected_duration_minutes,
            'notes' => $this->notes,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'carry_over_count' => (int) ($this->carry_over_count ?? 0),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'assigned_by' => $this->whenLoaded('assignedByUser', function() {
                return [
                    'id' => $this->assignedByUser->id,
                    'name' => $this->assignedByUser->name,
                ];
            }),
        ];
    }
}
