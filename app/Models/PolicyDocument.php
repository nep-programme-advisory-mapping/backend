<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PolicyDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'authority',
        'version',
        'date',
        'status',
        'file_url',
        'file_data',
        'file_name',
        'mime_type',
        'file_size',
        'created_by',
    ];

    /**
     * Hide binary BLOB data from normal JSON responses to keep API light and fast.
     */
    protected $hidden = [
        'file_data',
    ];

    protected $casts = [
        'date' => 'date',
        'file_size' => 'integer',
    ];

    protected $appends = ['file_url_full', 'has_file'];

    /**
     * The user who created this policy document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the full URL for the file.
     * Converts a relative storage path to a full URL the frontend can use.
     */
    public function getFileUrlFullAttribute(): ?string
    {
        if ($this->has_file || $this->file_name) {
            return url("/api/policy-documents/{$this->id}/file");
        }

        if ($this->file_url) {
            if (str_starts_with($this->file_url, 'http://') || str_starts_with($this->file_url, 'https://')) {
                return $this->file_url;
            }
            return Storage::disk('public')->url($this->file_url);
        }

        return null;
    }

    /**
     * Check if a file BLOB or file URL is present.
     */
    public function getHasFileAttribute(): bool
    {
        return !empty($this->file_data) || !empty($this->file_url);
    }
}
