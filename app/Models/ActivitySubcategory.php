<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySubcategory extends Model
{
    
    use HasFactory;

    protected $table = 'taxonomy_subcategories';

    protected $fillable = [
        'category_id',
        'code',
        'label',
        'is_active',
        'active',
        'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActiveAttribute()
    {
        return $this->is_active;
    }

    public function setActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value;
    }

    public function category()
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    public function items()
    {
        return $this->hasMany(ActivityItem::class, 'subcategory_id');
    }
}
