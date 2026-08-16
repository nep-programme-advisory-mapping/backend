<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdvisoryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'assign_to_staff_user_id',
        'coordinator_id',
        'programme_entry_id',
        'submitting_party',
        'document_name',
        'document_file',
        'analysis_scope',
        'analysis_scope_detail',
        'status',
        'section_profile',
        'section_gaps',
        'section_coordinators_notes',
        'final_note_file',
        'submitted_at',
        'delivered_at',
        'document_text',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $appends = ['final_note_file_url'];

    public function staffUser()
    {
        return $this->belongsTo(StaffUser::class, 'assign_to_staff_user_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function recommendations()
    {
        return $this->hasMany(AdvisoryRecommendation::class);
    }

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }

    /**
     * Get the full URL for the final note file.
     * Converts a relative storage path to a full URL the frontend can use.
     */
    public function getFinalNoteFileUrlAttribute(): ?string
    {
        if (! $this->final_note_file) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($this->final_note_file, 'http://') || str_starts_with($this->final_note_file, 'https://')) {
            return $this->final_note_file;
        }

        // Convert relative storage path to full URL
        return Storage::disk('public')->url($this->final_note_file);
    }
}
