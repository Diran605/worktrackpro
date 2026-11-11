<?php

namespace App\Http\Requests;

use App\Enums\WorkType;
use App\Enums\CompletionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ActivityLog::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'task_name' => ['required', 'string', 'max:255'],
            'project_client' => ['nullable', 'string', 'max:255'],
            'work_type' => ['required', new Enum(WorkType::class)],
            'start_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'end_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'output' => ['nullable', 'string'],
            'completion_type' => ['required', new Enum(CompletionType::class)],
            'is_planned' => ['required', 'boolean'],
            'daily_plan_id' => ['nullable', 'integer', 'exists:daily_plans,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
