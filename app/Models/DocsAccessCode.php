<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A code an admin gives to readers (e.g. a client) to unlock restricted manuals in the
 * documentation library. Each code chooses which manuals it opens and whether that includes
 * their restricted chapters.
 */
class DocsAccessCode extends Model
{
    use Auditable;

    /** Letters and digits that can't be confused with each other (no 0/O, 1/I/L). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'organisation_id',
        'name',
        'code',
        'code_hash',
        'expires_at',
        'is_active',
        'last_used_at',
        'use_count',
        'notes',
        'created_by',
    ];

    protected $hidden = ['code', 'code_hash'];

    protected $casts = [
        'code' => 'encrypted',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(System::class, 'docs_access_code_systems', 'access_code_id', 'system_id')
            ->withPivot('include_restricted')
            ->withTimestamps();
    }

    public function grants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocsAccessCodeSystem::class, 'access_code_id');
    }

    public function isUsable(): bool
    {
        return $this->is_active && (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Give this record a new random code (shown to admins; only its hash is used for lookup).
     */
    public function assignNewCode(): string
    {
        $code = collect(range(1, 3))
            ->map(fn () => collect(range(1, 4))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->join(''))
            ->join('-');

        $this->code = $code;
        $this->code_hash = self::hashCode($code);

        return $code;
    }

    /**
     * Codes are matched case-insensitively and ignoring spaces/dashes, so "k7qm 2xpa 9rtd" works.
     */
    public static function hashCode(string $code): string
    {
        $normalised = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return hash_hmac('sha256', $normalised, (string) config('app.key'));
    }
}
