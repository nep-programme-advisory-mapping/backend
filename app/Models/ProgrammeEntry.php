<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProgrammeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'budget_band_id',
        'programme_name',
        'start_year',
        'end_year',
        'ongoing',
        'fte_staff',
        'indirect_beneficiaries',
        'direct_beneficiaries',
        'method',
        'verified_date',
        'last_updated_at',
        'is_submitted',
        'created_by',
    ];

    protected $casts = [
        'ongoing' => 'boolean',
        'fte_staff' => 'decimal:2',
        'verified_date' => 'date',
        'last_updated_at' => 'datetime',
        'is_unverified' => 'boolean',
        'is_submitted' => 'boolean',
    ];
    
    protected static function booted(): void
    {
        static::creating(function (ProgrammeEntry $entry) {
            if (Auth::check()) {
                $entry->created_by = Auth::id();
            }
        });

        static::saving(function (ProgrammeEntry $entry) {
            $entry->last_updated_at = now();
            $entry->is_unverified = false; // any real save clears the stale flag

            if (Auth::check()) {
                $entry->last_updated_by = Auth::id();
            }
        });
    }

   
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function budgetBand()
    {
        return $this->belongsTo(BudgetBand::class);
    }

    public function keywords()
    {
        return $this->hasMany(EntryKeyword::class);
    }

    public function locations()
    {
        return $this->hasMany(ProgrammeLocation::class);
    }

    public function activities()
    {
        return $this->hasMany(ProgrammeActivity::class);
    }

    public function governmentAgreements()
    {
        return $this->hasMany(GovernmentAgreement::class);
    }

    public function advisoryRecommendations()
    {
        return $this->hasMany(AdvisoryRecommendation::class);
    }

    public function taxonomyOtherQueues()
    {
        return $this->hasMany(TaxonomyOtherQueue::class);
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
