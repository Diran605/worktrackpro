<?php

namespace App\Models;

use App\Support\Permissions;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An internal system or project in the registry. Each system has one manual (a tree of ManualSections).
 */
class System extends Model
{
    use Auditable, SoftDeletes;

    public const CRITICALITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    /** The business impact choices with what each one means (the product form). */
    public const IMPACT_OPTIONS = [
        'low' => 'Low — little effect if it stops working',
        'medium' => 'Medium — slows some work down',
        'high' => 'High — many staff or customers are affected',
        'critical' => 'Critical — the business cannot run without it',
    ];

    public const LIBRARY_HIDDEN = 'hidden';
    public const LIBRARY_PUBLIC = 'public';
    public const LIBRARY_RESTRICTED = 'restricted';

    public const LIBRARY_VISIBILITIES = [
        self::LIBRARY_HIDDEN => 'Not in library',
        self::LIBRARY_PUBLIC => 'Public',
        self::LIBRARY_RESTRICTED => 'Restricted',
    ];

    /** Slugs used by the library's own pages (docs/search, docs/access…), so systems can't take them. */
    public const RESERVED_SLUGS = ['search', 'access', 'request-access', 'feedback', 'sitemap', 'changes', 'pdf'];

    public const STATUSES = [
        'active' => 'Active',
        'deprecated' => 'Deprecated',
        'retired' => 'Retired',
    ];

    protected $fillable = [
        'organisation_id',
        'name',
        'slug',
        'summary',
        'description',
        'department_id',
        'category_id',
        'library_visibility',
        'criticality',
        'status',
        'url',
        'support_contact',
        'escalation_notes',
        'manual_version',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (System $system) {
            if (blank($system->slug)) {
                $system->slug = static::uniqueSlug($system->name, (int) $system->organisation_id);
            }
        });
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = \App\Support\SafeHtml::clean($value);
    }

    public static function uniqueSlug(string $name, int $organisationId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'system';
        $slug = $base;
        $i = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || static::withTrashed()
            ->where('organisation_id', $organisationId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class, 'category_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ManualRelease::class)->orderByDesc('published_at')->orderByDesc('id');
    }

    public function latestRelease(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ManualRelease::class)->latestOfMany('published_at');
    }

    public function isInLibrary(): bool
    {
        return in_array($this->library_visibility, [self::LIBRARY_PUBLIC, self::LIBRARY_RESTRICTED], true);
    }

    /**
     * Approved revisions that haven't been released in a version yet.
     */
    public function unreleasedRevisions()
    {
        return ManualSectionRevision::query()
            ->whereNull('release_id')
            ->whereIn('manual_section_id', ManualSection::withTrashed()->where('system_id', $this->id)->select('id'));
    }

    public function members(): HasMany
    {
        return $this->hasMany(SystemMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'system_members')->withPivot('role')->withTimestamps();
    }

    public function manualSections(): HasMany
    {
        return $this->hasMany(ManualSection::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ManualChangeRequest::class);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    /**
     * Users holding one of the given membership roles.
     */
    public function membersWithRole(string ...$roles)
    {
        return User::query()
            ->whereIn('id', $this->members()->whereIn('role', $roles)->select('user_id'))
            ->where('is_active', true)
            ->get();
    }

    public function owners()
    {
        return $this->membersWithRole(SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereIn('role', [SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER])
            ->exists();
    }

    /**
     * Who may approve manual changes: the system's owners and backup owners, organisation users
     * holding approve_kb, and super admins.
     */
    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->organisation_id !== $this->organisation_id) {
            return false;
        }

        return $this->isOwnedBy($user) || $user->can(Permissions::APPROVE_KB);
    }

    /**
     * Who receives "please review" messages: owners first; if the system has none, organisation approvers.
     */
    public function approverIds(): array
    {
        $owners = $this->owners()->pluck('id')->all();

        if ($owners !== []) {
            return $owners;
        }

        return User::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('is_active', true)
            ->permission(Permissions::APPROVE_KB)
            ->pluck('id')
            ->all();
    }

    public function nextManualVersion(bool $major): string
    {
        [$maj, $min] = array_map('intval', explode('.', $this->manual_version ?: '0.0') + [0, 0]);

        return $major ? ($maj + 1) . '.0' : "{$maj}." . ($min + 1);
    }
}
