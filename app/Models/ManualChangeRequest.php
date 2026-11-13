<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A proposed change to a manual: a new section, an edit, or a Word-file import.
 * Nothing reaches the published manual until an owner approves it.
 */
class ManualChangeRequest extends Model
{
    use Auditable;

    public const TYPE_CREATE = 'create';
    public const TYPE_EDIT = 'edit';
    public const TYPE_IMPORT = 'import';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Awaiting approval',
        self::STATUS_CHANGES_REQUESTED => 'Changes requested',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    public const TYPE_LABELS = [
        self::TYPE_CREATE => 'New section',
        self::TYPE_EDIT => 'Edit',
        self::TYPE_IMPORT => 'Import',
    ];

    /** Statuses in which the author may still change the proposal. */
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_CHANGES_REQUESTED];

    protected $fillable = [
        'system_id',
        'manual_section_id',
        'type',
        'kind',
        'title',
        'body',
        'file_path',
        'file_name',
        'file_size',
        'page_count',
        'page_from',
        'page_to',
        'payload',
        'proposed_parent_id',
        'proposed_after_id',
        'change_summary',
        'status',
        'base_version',
        'author_id',
        'reviewer_id',
        'review_comment',
        'is_major',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_major' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ManualSection::class, 'manual_section_id');
    }

    public function proposedParent(): BelongsTo
    {
        return $this->belongsTo(ManualSection::class, 'proposed_parent_id');
    }

    public function proposedAfter(): BelongsTo
    {
        return $this->belongsTo(ManualSection::class, 'proposed_after_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(DailyPlan::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Sections proposed by a Word import, flattened in order: [['title', 'body', 'depth'], ...].
     */
    public function importSections(): array
    {
        return $this->payload['sections'] ?? [];
    }

    /** A PDF split into sections by page range (rather than a Word import). */
    public function isPdfImport(): bool
    {
        return $this->type === self::TYPE_IMPORT && $this->kind === ManualSection::KIND_UPLOADED_PDF;
    }

    /** "Word import" / "PDF import" / "New section" / "Edit". */
    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_IMPORT) {
            return $this->isPdfImport() ? 'PDF import' : 'Word import';
        }

        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /** Audit entries belong to the system's organisation. */
    public function auditOrganisationId(): ?int
    {
        return System::withTrashed()->whereKey($this->system_id)->value('organisation_id');
    }
}
