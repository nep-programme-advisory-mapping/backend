<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxonomyOtherQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_entry_id',
        'item_id',
        'other_text',
        'suggested_subcategory_id',
        'promoted_item_id',
        'frequency',
        'status',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }

    public function item()
    {
        return $this->belongsTo(ActivityItem::class, 'item_id');
    }

    public function suggestedSubcategory()
    {
        return $this->belongsTo(ActivitySubcategory::class, 'suggested_subcategory_id');
    }

    public function promotedItem()
    {
        return $this->belongsTo(ActivityItem::class, 'promoted_item_id');
    }
}
