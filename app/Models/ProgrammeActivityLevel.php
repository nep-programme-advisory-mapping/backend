<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgrammeActivityLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_activity_id',
        'education_level_id',
    ];

    public function programmeActivity()
    {
        return $this->belongsTo(ProgrammeActivity::class);
    }

    public function educationLevel()
    {
        return $this->belongsTo(EducationLevel::class);
    }
}
