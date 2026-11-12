<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A group of readers in the documentation library, e.g. "Teachers" or "Finance".
 * Chapters meant for an audience can only be read by staff and by access codes that open it.
 */
class DocsAudience extends Model
{
    use Auditable;

    protected $fillable = ['organisation_id', 'name', 'slug', 'description', 'position'];

    protected static function booted(): void
    {
        static::saving(function (DocsAudience $audience) {
            if (blank($audience->slug) || $audience->isDirty('name')) {
                $base = Str::slug($audience->name) ?: 'audience';
                $slug = $base;
                $i = 2;
                while (static::where('organisation_id', $audience->organisation_id)
                    ->where('slug', $slug)
                    ->whereKeyNot($audience->getKey())
                    ->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $audience->slug = $slug;
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(ManualSection::class, 'manual_section_audience', 'audience_id', 'manual_section_id');
    }
}
