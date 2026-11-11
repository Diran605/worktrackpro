<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'abandoned_timer_hours',
        'carry_over_flag_threshold',
        'inbox_max_attachment_kb',
        'inbox_allowed_mime_types',
        'abandoned_session_close_time',
    ];

    protected $casts = [
        'inbox_allowed_mime_types' => 'array',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

