<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\PlanStatus;
use App\Enums\WorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'work_type' => ['sometimes', 'required', new Enum(WorkType::class)],
            'work_type_id' => ['nullable', 'integer', Rule::exists('work_types', 'id')
                ->where('organisation_id', $this->user()->organisation_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')],
            'project_client_id' => ['nullable', 'integer', 'exists:project_clients,id'],
            'expected_duration_minutes' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', new Enum(PlanStatus::class)],
        ];
    }
}
