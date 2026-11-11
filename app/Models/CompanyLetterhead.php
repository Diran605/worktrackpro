<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLetterhead extends Model
{
    use HasFactory;

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

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

