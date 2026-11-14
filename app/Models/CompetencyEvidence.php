<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** A screenshot attached to an answer. */
class CompetencyEvidence extends Model
{
    protected $table = 'competency_evidence';

    protected $fillable = ['competency_submission_id', 'path', 'original_name', 'mime', 'size'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompetencySubmission::class, 'competency_submission_id');
    }

    /** A link that works for two hours (handed out only to people allowed to see the answer). */
    public function url(): string
    {
        return URL::temporarySignedRoute('kb.competency-evidence', now()->addHours(2), ['evidence' => $this->id]);
    }
}
