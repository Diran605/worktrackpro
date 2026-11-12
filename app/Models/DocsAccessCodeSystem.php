<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One manual an access code opens (and whether it includes the manual's restricted chapters).
 */
class DocsAccessCodeSystem extends Model
{
    protected $table = 'docs_access_code_systems';

    protected $fillable = ['access_code_id', 'system_id', 'include_restricted'];

    protected $casts = ['include_restricted' => 'boolean'];

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(DocsAccessCode::class, 'access_code_id');
    }

    /** Audiences this code opens in this product (chapters meant for them). */
    public function audiences(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DocsAudience::class, 'docs_access_code_audiences', 'grant_id', 'audience_id')
            ->orderBy('position')
            ->orderBy('name');
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }
}
