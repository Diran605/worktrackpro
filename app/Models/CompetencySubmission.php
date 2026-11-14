<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's answer to a competency check. Draft → submitted → approved, or sent back
 * (changes_requested) to update and submit again.
 */
class CompetencySubmission extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Waiting for review',
        self::STATUS_CHANGES_REQUESTED => 'Sent back',
        self::STATUS_APPROVED => 'Passed',
    ];

    protected $fillable = [
        'competency_check_id',
        'user_id',
        'status',
        'answer',
        'video_links',
        'times_submitted',
        'submitted_at',
        'reviewer_id',
        'reviewed_at',
        'review_comment',
        'manual_version',
        'expires_at',
        'needs_recheck',
    ];

    protected $casts = [
        'video_links' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
        'needs_recheck' => 'boolean',
        'times_submitted' => 'integer',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(CompetencyCheck::class, 'competency_check_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(CompetencyEvidence::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompetencyReview::class)->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_CHANGES_REQUESTED]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CHANGES_REQUESTED], true);
    }

    /** An approval that still counts: not expired and not waiting for a re-check. */
    public function isValidPass(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && !$this->needs_recheck
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
