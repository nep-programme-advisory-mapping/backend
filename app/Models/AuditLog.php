<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable record of a destructive action (delete) taken through the API.
 * Written by App\Services\AuditLogger — see that class for the write path.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_label',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
