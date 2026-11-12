<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLetterhead extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'organisation_id',
        'company_name',
        'header_image_path',
        'footer_image_path',
        'header_height_px',
        'footer_height_px',
        'accent_color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Only one letterhead per organisation is in use: activating one switches the others off.
     */
    protected static function booted(): void
    {
        static::saved(function (CompanyLetterhead $letterhead) {
            if ($letterhead->is_active) {
                static::query()
                    ->where('organisation_id', $letterhead->organisation_id)
                    ->whereKeyNot($letterhead->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

