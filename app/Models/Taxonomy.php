<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    use HasFactory;

    protected $table = 'taxonomy_categories';

    protected $fillable = [
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

    public function subcategories()
    {
        return $this->hasMany(ActivitySubcategory::class, 'category_id');
    }

    public function programmes()
    {
        return $this->hasMany(Programme::class, 'taxonomy_id');
    }
}