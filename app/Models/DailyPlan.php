<?php

namespace App\Models;

use App\Enums\PlanStatus;
use App\Enums\Priority;
use App\Enums\TimerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class DailyPlan extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'assigned_by',
        'organisation_id',
        'date',
        'task_name',
        'project_client',
        'project_client_id',
        'priority',
        'expected_duration_minutes',
        'notes',
        'status',
        'timer_status',
        'timer_started_at',
        'timer_accumulated_seconds',
        'work_session_id',
        'is_assigned',
        'task_template_id',
        'personal_recurring_task_id',
        'carried_from_plan_id',
        'carry_over_count',
    ];

    protected $casts = [
        'date' => 'date',
        'priority' => Priority::class,
        'status' => PlanStatus::class,
        'timer_status' => TimerStatus::class,
        'timer_started_at' => 'datetime',
        'is_assigned' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function timerPauses(): HasMany
    {
        return $this->hasMany(TimerPause::class);
    }

    public function carriedFromPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class, 'carried_from_plan_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function projectClient(): BelongsTo
    {
        return $this->belongsTo(ProjectClient::class);
    }
}
