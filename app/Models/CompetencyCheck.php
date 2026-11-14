<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A practical task that shows someone can do the job on a product, with a checklist of what a
 * good answer includes. Required for some people (by role, department or name), optional for others.
 */
class CompetencyCheck extends Model
{
    use Auditable, SoftDeletes;

    public const WEIGHTS = [
        1 => 'Standard',
        2 => 'Important',
        3 => 'Essential',
    ];

    protected $fillable = [
        'system_id',
        'title',
        'scenario',
        'criteria',
        'weight',
        'is_required',
        'required_for',
        'valid_for_days',
        'section_ids',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'criteria' => 'array',
        'required_for' => 'array',
        'section_ids' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'weight' => 'integer',
        'valid_for_days' => 'integer',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CompetencySubmission::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->whereHas('system', fn ($q) => $q->where('organisation_id', $organisationId));
    }

    /** @return array<int, string> the checklist, without empty lines */
    public function criteriaList(): array
    {
        return array_values(array_filter(array_map(
            fn ($c) => trim(is_array($c) ? (string) ($c['criterion'] ?? '') : (string) $c),
            $this->criteria ?? []
        ), fn ($c) => $c !== ''));
    }

    /** Must this person pass it? (Optional checks are never required.) */
    public function isRequiredFor(User $user): bool
    {
        if (!$this->is_required || !$this->is_active) {
            return false;
        }

        $for = $this->required_for ?? [];

        if (!empty($for['everyone'])) {
            return true;
        }

        if (in_array($user->id, array_map('intval', $for['users'] ?? []), true)) {
            return true;
        }

        if ($user->department_id && in_array($user->department_id, array_map('intval', $for['departments'] ?? []), true)) {
            return true;
        }

        $roles = array_map('strval', $for['roles'] ?? []);

        return $roles !== [] && $user->getRoleNames()->intersect($roles)->isNotEmpty();
    }

    /** "Everyone", "Sales, Support and 2 people"… */
    public function requiredForLabel(): string
    {
        if (!$this->is_required) {
            return 'Optional';
        }

        $for = $this->required_for ?? [];

        if (!empty($for['everyone'])) {
            return 'Everyone';
        }

        $parts = [];
        $roles = $for['roles'] ?? [];
        if ($roles !== []) {
            $parts[] = implode(', ', array_map(fn ($r) => ucwords(str_replace('_', ' ', $r)), $roles));
        }
        $departments = $for['departments'] ?? [];
        if ($departments !== []) {
            $parts[] = Department::query()->whereIn('id', $departments)->orderBy('name')->pluck('name')->join(', ');
        }
        $users = count($for['users'] ?? []);
        if ($users > 0) {
            $parts[] = $users . ' ' . ($users === 1 ? 'person' : 'people');
        }

        return $parts === [] ? 'Nobody yet' : implode(' · ', $parts);
    }
}
