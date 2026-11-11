<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDailyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\DailyPlan::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'task_name' => ['required', 'string', 'max:255'],
            'project_client' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', new Enum(Priority::class)],
            'expected_duration_minutes' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', new Enum(PlanStatus::class)],
        ];
    }
}
