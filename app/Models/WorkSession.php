<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organisation_id',
        'date',
        'clock_in',
        'clock_out',
        'total_minutes',
        'status',
        'clock_in_ip',
        'clock_out_ip',
        'user_agent',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'status' => SessionStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyPlan::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function reopenRequests(): HasMany
    {
        return $this->hasMany(SessionReopenRequest::class);
    }

    public function scopeForDate($query, string|\DateTimeInterface $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SessionStatus::Active->value);
    }

    public function scopeSystemClosed($query)
    {
        return $query->where('status', SessionStatus::SystemClosed->value);
    }
}

