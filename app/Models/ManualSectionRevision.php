<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published version of a section. Never edited after creation.
 */
class ManualSectionRevision extends Model
{
    protected $fillable = [
        'manual_section_id',
        'version',
        'title',
        'body',
        'file_path',
        'file_name',
        'file_size',
        'page_count',
        'page_from',
        'page_to',
        'change_summary',
        'manual_version',
        'release_id',
        'author_id',
        'approved_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ManualSection::class, 'manual_section_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(ManualRelease::class, 'release_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
