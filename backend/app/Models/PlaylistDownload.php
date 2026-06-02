<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistDownload extends Model
{
    use HasFactory;

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'playlist_url',
        'title',
        'total_items',
        'completed_items',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_items' => 'integer',
        'completed_items' => 'integer',
    ];

    /**
     * Get the user that owns the playlist download.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all items in this playlist download.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /**
     * Update overall progress based on completed items.
     */
    public function updateProgress(): void
    {
        $completed = $this->downloads()->where('status', Download::STATUS_COMPLETED)->count();
        $failed = $this->downloads()->where('status', Download::STATUS_FAILED)->count();
        
        $this->update([
            'completed_items' => $completed,
            'status' => $this->calculateStatus($completed, $failed),
        ]);
    }

    /**
     * Calculate playlist status based on item statuses.
     */
    private function calculateStatus(int $completed, int $failed): string
    {
        $total = $this->total_items;
        
        if ($completed === $total) {
            return self::STATUS_COMPLETED;
        }
        
        if ($completed + $failed === $total) {
            return self::STATUS_FAILED;
        }
        
        return self::STATUS_PROCESSING;
    }

    /**
     * Get overall progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return round(($this->completed_items / $this->total_items) * 100, 2);
    }
}
