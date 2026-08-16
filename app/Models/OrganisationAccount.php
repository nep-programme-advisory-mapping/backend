<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganisationAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'account_name',
        'allocated_amount',
        'used_amount',
        'currency',
        'period_start',
        'period_end',
        'status',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function remainingBalance(): float
    {
        return (float) $this->allocated_amount - (float) $this->used_amount;
    }
}