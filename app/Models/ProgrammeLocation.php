<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgrammeLocation extends Model
{
    
    use HasFactory;

    protected $table = 'programme_geography';

    protected $fillable = [
        'programme_entry_id',
        'province_id',
        'district_id',
        'commune_id',
        'village_id',
        'country',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }
}
