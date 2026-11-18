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
        self::ROLE_REVIEWER => 'Reviewer (competency checks)',
        self::ROLE_CONTACT => 'Support contact',
    ];

    /** Review answers to the product's competency checks. */
    public const COMPETENCY_REVIEWER_ROLES = [self::ROLE_OWNER, self::ROLE_BACKUP_OWNER, self::ROLE_REVIEWER];

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
