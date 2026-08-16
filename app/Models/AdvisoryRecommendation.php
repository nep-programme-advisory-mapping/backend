<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvisoryRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisory_note_id',
        'programme_entry_id',
        'organisation_name',
        'programme_name',
        'type',
        'relational',
        'rationale',
    ];

    public function advisoryNote()
    {
        return $this->belongsTo(AdvisoryNote::class);
    }

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }
}
