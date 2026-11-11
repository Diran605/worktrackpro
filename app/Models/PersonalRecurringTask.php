<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecurringTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'work_type',
        'priority',
        'expected_duration_minutes',
        'recurrence_type',
        'recurrence_day',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

