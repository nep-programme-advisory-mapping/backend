<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernmentAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_entry_id',
        'counterpart_agency',
        'status',
        'institution_name',
        'nature',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }
}
