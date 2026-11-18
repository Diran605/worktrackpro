<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reviewer's decision on an answer (kept so people see the whole conversation). */
class CompetencyReview extends Model
{
    protected $fillable = ['competency_submission_id', 'reviewer_id', 'decision', 'criteria_met', 'comment'];

    protected $casts = [
        'criteria_met' => 'array',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
