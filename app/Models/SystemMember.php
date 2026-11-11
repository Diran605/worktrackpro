<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMember extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_BACKUP_OWNER = 'backup_owner';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_CONTACT = 'contact';

    public const ROLES = [
        self::ROLE_OWNER => 'Owner',
        self::ROLE_BACKUP_OWNER => 'Backup owner',
        self::ROLE_REVIEWER => 'Reviewer',
        self::ROLE_CONTACT => 'Support contact',
    ];

    protected $fillable = ['system_id', 'user_id', 'role'];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
