<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    use HasFactory;

    protected $appends = ['logo_url'];

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'member_since',
        'status',
        'last_inactive_at',
        'logo_path',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function programmeEntries()
    {
        return $this->hasMany(ProgrammeEntry::class);
    }

    public function accounts()
    {
        return $this->hasMany(OrganisationAccount::class);
    }

    public function programmes()
    {
        return $this->hasMany(Programme::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        // New format: "fileId|/folder/filename.jpg"
        if (str_contains($this->logo_path, '|')) {
            $filePath = explode('|', $this->logo_path)[1];
            return rtrim(config('services.imagekit.url_endpoint'), '/') . $filePath;
        }

        // Old format: full URL stored directly
        if (str_starts_with($this->logo_path, 'http')) {
            return $this->logo_path;
        }

        return null;
    }
}
