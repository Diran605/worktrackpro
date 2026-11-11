<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that a user read a particular version of a section (drives study progress).
 */
class ManualRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'manual_section_id', 'version', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
