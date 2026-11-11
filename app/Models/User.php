<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Support\Permissions;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes, Auditable;

    protected $fillable = [
        'organisation_id',
        'department_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyPlan::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function weeklyStats(): HasMany
    {
        return $this->hasMany(WeeklyStatsCache::class);
    }

    /**
     * The user's organisation timezone (falls back to the app timezone).
     */
    public function timezone(): string
    {
        return \App\Support\LocalTime::timezoneFor($this->organisation_id);
    }

    public function localNow(): \Carbon\CarbonImmutable
    {
        return \App\Support\LocalTime::now($this->organisation_id);
    }

    /**
     * Today's date (Y-m-d) in the user's organisation.
     */
    public function localToday(): string
    {
        return \App\Support\LocalTime::today($this->organisation_id);
    }

    public function systemMemberships(): HasMany
    {
        return $this->hasMany(SystemMember::class);
    }

    /**
     * IDs of systems where this user can approve documentation (owner or backup owner).
     */
    public function ownedSystemIds(): array
    {
        return $this->systemMemberships()
            ->whereIn('role', [SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER])
            ->pluck('system_id')
            ->all();
    }

    /**
     * Every active admin in this user's department (or organisation, if the user has no department).
     */
    public function departmentAdminIds(): array
    {
        return static::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->role('admin')
            ->pluck('id')
            ->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->organisation && !$this->organisation->is_active) {
            return false;
        }

        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Portal-only permissions (own work, inbox, reading/writing docs) don't open /admin.
        $adminPermissions = $this->getAllPermissions()
            ->pluck('name')
            ->diff(Permissions::PORTAL_ONLY);

        if ($adminPermissions->isNotEmpty()) {
            return true;
        }

        // System owners review documentation in Filament, even if their role is "worker".
        return $this->ownedSystemIds() !== [];
    }
}
