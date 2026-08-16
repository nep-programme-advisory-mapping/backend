<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntryKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_entry_id',
        'keyword',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }
}
