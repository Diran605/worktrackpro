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
        'carry_over_decision',
        'carry_over_decided_at',
        'work_type',
        'system_id',
        'manual_change_request_id',
    ];

    protected $casts = [
        'date' => 'date',
        'carry_over_decided_at' => 'datetime',
        'priority' => Priority::class,
        'status' => PlanStatus::class,
        'timer_status' => TimerStatus::class,
        'timer_started_at' => 'datetime',
        'is_assigned' => 'boolean',
    ];

    /**
     * Excludes plans the worker chose to "leave for now" today, so they don't block clock-out again.
     */
    public function scopeNotLeftToday($query, User $user)
    {
        // Stored timestamps are UTC; "today" is the organisation's day.
        $startOfToday = \App\Support\LocalTime::startOfToday($user->organisation_id);

        return $query->where(function ($q) use ($startOfToday) {
            $q->whereNull('carry_over_decided_at')
                ->orWhere('carry_over_decision', '!=', 'leave')
                ->orWhere('carry_over_decided_at', '<', $startOfToday);
        });
    }

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

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function manualChangeRequest(): BelongsTo
    {
        return $this->belongsTo(ManualChangeRequest::class);
    }

    public function projectClient(): BelongsTo
    {
        return $this->belongsTo(ProjectClient::class);
    }
}
