<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'password',
        'role',
        'status',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
    ];

    public function advisoryNotes()
    {
        return $this->hasMany(AdvisoryNote::class, 'assign_to_staff_user_id');
    }
}
