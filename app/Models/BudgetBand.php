<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetBand extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'min_amount',
        'max_amount',
    ];

    public function programmeEntries()
    {
        return $this->hasMany(ProgrammeEntry::class);
    }
}
