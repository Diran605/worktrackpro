<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One node in a system's manual tree (chapter, section or sub-section).
 * Content lives in revisions; current_revision_id points at the published one.
 */
class ManualSection extends Model
{
    use Auditable, SoftDeletes;

    public const KIND_WRITTEN = 'written';
    public const KIND_UPLOADED_PDF = 'uploaded_pdf';

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'system_id',
        'parent_id',
        'position',
        'number',
        'sort_key',
        'depth',
        'title',
        'kind',
        'status',
        'current_revision_id',
        'last_verified_at',
        'last_verified_by',
        'created_by',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ManualSectionRevision::class)->orderByDesc('version');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ManualSectionRevision::class, 'current_revision_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function currentVersion(): int
    {
        return (int) ($this->currentRevision?->version ?? 0);
    }

    /**
     * IDs of this section and everything below it.
     */
    public function descendantIdsAndSelf(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
