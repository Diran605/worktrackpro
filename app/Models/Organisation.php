<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

class Organisation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'docs_domain',
        'docs_title',
        'docs_tagline',
        'docs_footer_links',
        'docs_banners',
        'timezone',
        'is_active',
        'primary_color',
        'secondary_color',
        'logo',
        'letterhead',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'docs_footer_links' => 'array',
        'docs_banners' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn (Organisation $org) => \App\Support\LocalTime::forget($org->id));
        static::created(fn (Organisation $org) => WorkType::createDefaultsFor($org->id));
    }

    public function localNow(): \Carbon\CarbonImmutable
    {
        return \App\Support\LocalTime::now($this->id);
    }

    public function localToday(): string
    {
        return \App\Support\LocalTime::today($this->id);
    }

    public function docsAudiences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocsAudience::class)->orderBy('position')->orderBy('name');
    }

    public function systemCategories(): HasMany
    {
        return $this->hasMany(SystemCategory::class)->orderBy('position')->orderBy('name');
    }

    public function docsTitle(): string
    {
        return $this->docs_title ?: "{$this->name} Documentation";
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyPlan::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function workTypes(): HasMany
    {
        return $this->hasMany(WorkType::class);
    }

    public function projectClients(): HasMany
    {
        return $this->hasMany(ProjectClient::class);
    }
}
