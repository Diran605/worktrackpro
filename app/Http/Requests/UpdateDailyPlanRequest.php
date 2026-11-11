<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\PlanStatus;
use App\Enums\WorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateDailyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plan'));
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date'],
            'task_name' => ['sometimes', 'required', 'string', 'max:255'],
            'project_client' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'required', new Enum(Priority::class)],
            'work_type' => ['sometimes', 'required', new Enum(WorkType::class)],
            'work_type_id' => ['nullable', 'integer', Rule::exists('work_types', 'id')
                ->where('organisation_id', $this->user()->organisation_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')],
            'project_client_id' => ['nullable', 'integer', 'exists:project_clients,id'],
            'expected_duration_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', new Enum(PlanStatus::class)],
        ];
    }
}
