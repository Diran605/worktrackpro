<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'letter_type',
        'name',
        'subject_template',
        'body_template',
        'is_system_default',
        'requires_acknowledgement',
        'created_by',
        'last_edited_by',
    ];

    protected $casts = [
        'is_system_default' => 'boolean',
        'requires_acknowledgement' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }
}

