<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader asking for access to a restricted manual from the documentation library.
 */
class DocsAccessRequest extends Model
{
    protected $fillable = ['system_id', 'name', 'email', 'reason', 'ip', 'handled_at', 'handled_by'];

    protected $casts = ['handled_at' => 'datetime'];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
