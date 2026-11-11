<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
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
            'expected_duration_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', new Enum(PlanStatus::class)],
        ];
    }
}
