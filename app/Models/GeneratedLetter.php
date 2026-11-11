<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'worker_id',
        'generated_by',
        'letter_template_id',
        'letter_type',
        'subject',
        'body_snapshot',
        'pdf_path',
        'custom_fields',
        'generated_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'generated_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }
}

