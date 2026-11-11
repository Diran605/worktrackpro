<?php

namespace App\Models;

use App\Enums\WorkType as WorkCategory;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organisation's own work type (e.g. "Client calls"). Each belongs to a category
 * (Direct / Indirect / Growth — the App\Enums\WorkType enum) used by the productivity stats.
 */
class WorkType extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    public const DEFAULTS = [
        ['name' => 'Direct', 'category' => 'direct', 'color' => 'success'],
        ['name' => 'Indirect', 'category' => 'indirect', 'color' => 'warning'],
        ['name' => 'Growth', 'category' => 'growth', 'color' => 'info'],
    ];

    protected $fillable = [
        'organisation_id',
        'name',
        'category',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'category' => WorkCategory::class,
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public static function createDefaultsFor(int $organisationId): void
    {
        foreach (self::DEFAULTS as $type) {
            static::create($type + ['organisation_id' => $organisationId, 'is_active' => true]);
        }
    }
}
