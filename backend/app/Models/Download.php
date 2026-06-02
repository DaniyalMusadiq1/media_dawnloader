<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Download extends Model
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
    const STATUS_SCHEDULED = 'scheduled';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'playlist_download_id',
        'original_url',
        'title',
        'type',
        'quality',
        'file_size',
        'duration',
        'thumbnail',
        'status',
        'scheduled_at',
        'error_message',
        'file_path',
        'download_url',
        'share_token',
        'progress',
        'speed',
        'eta',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'scheduled_at' => 'datetime',
        'progress' => 'integer',
        'eta' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Download $download) {
            if (empty($download->share_token)) {
                $download->share_token = Str::random(8);
            }
        });
    }

    /**
     * Get the user that owns the download.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the playlist download this item belongs to.
     */
    public function playlistDownload(): BelongsTo
    {
        return $this->belongsTo(PlaylistDownload::class);
    }

    /**
     * Scope a query to only include downloads for a user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include pending/processing downloads.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_SCHEDULED]);
    }

    /**
     * Scope a query to only include completed downloads.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Check if download is part of a playlist.
     */
    public function isPlaylistItem(): bool
    {
        return $this->playlist_download_id !== null;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Generate signed download URL.
     */
    public function generateSignedUrl(int $expiration = 3600): string
    {
        if (!$this->file_path) {
            return '';
        }

        return url("/api/download/{$this->id}/file?token=" . hash_hmac('sha256', "{$this->id}:{$expiration}", config('app.key')));
    }

    /**
     * Update progress and broadcast event.
     */
    public function updateProgress(int $progress, ?string $speed = null, ?int $eta = null): void
    {
        $this->update([
            'progress' => $progress,
            'speed' => $speed,
            'eta' => $eta,
        ]);

        // Broadcast progress event
        if ($progress < 100) {
            broadcast(new \App\Events\DownloadProgress($this))->toOthers();
        } else {
            $this->update(['status' => self::STATUS_COMPLETED]);
            broadcast(new \App\Events\DownloadCompleted($this))->toOthers();
        }
    }
}
