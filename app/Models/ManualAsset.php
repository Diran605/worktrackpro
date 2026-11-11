<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * A screenshot or image used inside a written section. Stored on the private disk and served
 * through short-lived signed URLs, because an img tag cannot send the API bearer token.
 */
class ManualAsset extends Model
{
    protected $fillable = [
        'system_id',
        'path',
        'original_name',
        'mime',
        'size',
        'width',
        'height',
        'uploaded_by',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function signedUrl(int $minutes = 120): string
    {
        return URL::temporarySignedRoute('kb.asset', now()->addMinutes($minutes), ['asset' => $this->id]);
    }
}
