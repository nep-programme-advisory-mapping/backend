<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgrammeActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_entry_id',
        'activity_item_id',
        'is_primary',
        'inclusion_group',
        'inclusion_type',
        'other_text',
        'taxonomy_version',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }

    public function activityItem()
    {
        return $this->belongsTo(ActivityItem::class);
    }

    public function activityLevels()
    {
        return $this->hasMany(ProgrammeActivityLevel::class);
    }
}
