<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'guest_token',
        'preferences',
        'is_premium',
        'api_token',
        'premium_expires_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'preferences' => 'array',
        'is_premium' => 'boolean',
        'premium_expires_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->guest_token)) {
                $user->guest_token = Str::random(32);
            }
            
            if (empty($user->preferences)) {
                $user->preferences = [
                    'default_format' => 'video',
                    'default_quality' => 'best',
                    'timezone' => 'UTC',
                ];
            }
        });
    }

    /**
     * Get the user's downloads.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /**
     * Get the user's playlist downloads.
     */
    public function playlistDownloads(): HasMany
    {
        return $this->hasMany(PlaylistDownload::class);
    }

    /**
     * Check if user is premium.
     */
    public function isPremium(): bool
    {
        if ($this->is_premium) {
            return $this->premium_expires_at === null || $this->premium_expires_at->isFuture();
        }
        
        return false;
    }

    /**
     * Get daily download limit.
     */
    public function getDailyLimitAttribute(): int
    {
        return $this->isPremium() 
            ? (int) config('app.max_downloads_premium', 100)
            : (int) config('app.max_downloads_free', 50);
    }

    /**
     * Get file retention days.
     */
    public function getFileRetentionDaysAttribute(): int
    {
        return $this->isPremium()
            ? (int) config('app.file_retention_days_premium', 30)
            : (int) config('app.file_retention_days_free', 7);
    }

    /**
     * Generate a new API token for extension.
     */
    public function generateApiToken(): string
    {
        $this->api_token = Str::random(64);
        $this->save();
        
        return $this->api_token;
    }

    /**
     * Find or create user by guest token.
     */
    public static function findByGuestToken(string $token): ?self
    {
        return static::where('guest_token', $token)->first();
    }
}
