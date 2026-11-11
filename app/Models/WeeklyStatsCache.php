<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyStatsCache extends Model
{
    use HasFactory;

    protected $table = 'weekly_stats_cache';

    protected $fillable = [
        'user_id',
        'organisation_id',
        'week_start',
        'total_planned',
        'total_completed_planned',
        'execution_rate',
        'direct_minutes',
        'indirect_minutes',
        'growth_minutes',
        'unplanned_count',
        'total_log_count',
        'recalculated_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'execution_rate' => 'decimal:2',
        'recalculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
