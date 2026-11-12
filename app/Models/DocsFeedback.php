<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Was this helpful?" answers from the documentation library (one per visitor per section).
 */
class DocsFeedback extends Model
{
    protected $table = 'docs_feedback';

    protected $fillable = ['manual_section_id', 'helpful', 'comment', 'visitor_hash'];

    protected $casts = ['helpful' => 'boolean'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ManualSection::class, 'manual_section_id');
    }
}
