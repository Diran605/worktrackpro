<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Groups systems on the documentation library's home page.
 */
class SystemCategory extends Model
{
    protected $fillable = ['organisation_id', 'name', 'slug', 'description', 'icon', 'position'];

    protected static function booted(): void
    {
        static::saving(function (SystemCategory $category) {
            if (blank($category->slug) || $category->isDirty('name')) {
                $base = Str::slug($category->name) ?: 'category';
                $slug = $base;
                $i = 2;
                while (static::where('organisation_id', $category->organisation_id)
                    ->where('slug', $slug)
                    ->whereKeyNot($category->getKey())
                    ->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $category->slug = $slug;
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function systems(): HasMany
    {
        return $this->hasMany(System::class, 'category_id');
    }
}
