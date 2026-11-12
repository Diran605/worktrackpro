<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published version of a system's manual ("Version 1.1: added refund steps…").
 * The revisions approved since the previous release belong to it.
 */
class ManualRelease extends Model
{
    use Auditable;

    protected $fillable = ['system_id', 'version', 'is_major', 'summary', 'published_by', 'published_at'];

    protected $casts = [
        'is_major' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ManualSectionRevision::class, 'release_id');
    }

    /** Audit entries belong to the system's organisation. */
    public function auditOrganisationId(): ?int
    {
        return System::withTrashed()->whereKey($this->system_id)->value('organisation_id');
    }
}
