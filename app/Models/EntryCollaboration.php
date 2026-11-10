<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryCollaboration extends Model
{
    protected $fillable = [
        'programme_entry_id',
        'organisation_name',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }
}
