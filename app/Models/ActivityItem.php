<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityItem extends Model
{
   
    use HasFactory;

    protected $table = 'taxonomy_items';

    protected $fillable = [
        'subcategory_id',
        'code',
        'label',
        'is_active',
        'active',
        'is_other',
        'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_other' => 'boolean',
    ];

    public function getActiveAttribute()
    {
        return $this->is_active;
    }

    public function setActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value;
    }

    public function subcategory()
    {
        return $this->belongsTo(ActivitySubcategory::class, 'subcategory_id');
    }

    public function programmeActivities()
    {
        return $this->hasMany(ProgrammeActivity::class);
    }

    public function taxonomyOtherQueues()
    {
        return $this->hasMany(TaxonomyOtherQueue::class, 'item_id');
    }
}
