<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_name',
    ];

    public function districts()
    {
        return $this->hasMany(District::class);
    }

    public function locations()
    {
        return $this->hasMany(ProgrammeLocation::class);
    }
};
