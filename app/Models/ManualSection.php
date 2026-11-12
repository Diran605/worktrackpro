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

    public const VISIBILITY_INHERIT = 'inherit';
    public const VISIBILITY_RESTRICTED = 'restricted';

    protected $fillable = [
        'system_id',
        'parent_id',
        'position',
        'number',
        'sort_key',
        'depth',
        'title',
        'slug',
        'kind',
        'status',
        'visibility',
        'current_revision_id',
        'last_verified_at',
        'last_verified_by',
        'created_by',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A readable, stable address for the documentation library (kept when the title changes).
        static::creating(function (ManualSection $section) {
            if (blank($section->slug)) {
                $section->slug = static::uniqueSlug((int) $section->system_id, (string) $section->title);
            }
        });
    }

    /** Addresses used by the library's own pages under /docs/{system}/… */
    public const RESERVED_SLUGS = ['changes', 'pdf', 'request-access'];

    public static function uniqueSlug(int $systemId, string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'section';
        $slug = $base;
        $i = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true)
            || static::withTrashed()->where('system_id', $systemId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function isRestricted(): bool
    {
        return $this->visibility === self::VISIBILITY_RESTRICTED;
    }

    /** Who the chapter is meant for in the documentation library (none = everyone who can open the manual). */
    public function audiences(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DocsAudience::class, 'manual_section_audience', 'manual_section_id', 'audience_id')
            ->orderBy('position')
            ->orderBy('name');
    }

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

    /** Audit entries belong to the system's organisation. */
    public function auditOrganisationId(): ?int
    {
        return System::withTrashed()->whereKey($this->system_id)->value('organisation_id');
    }
}
