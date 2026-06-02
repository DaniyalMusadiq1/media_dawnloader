<?php

namespace App\Events;

use App\Models\Download;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DownloadCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The download instance.
     */
    public Download $download;

    /**
     * Create a new event instance.
     */
    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('download-progress.' . $this->download->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->download->id,
            'status' => $this->download->status,
            'title' => $this->download->title,
            'thumbnail' => $this->download->thumbnail,
            'file_size' => $this->download->file_size,
            'formatted_file_size' => $this->download->formatted_file_size,
            'duration' => $this->download->duration,
            'formatted_duration' => $this->download->formatted_duration,
            'download_url' => $this->download->download_url,
            'share_token' => $this->download->share_token,
        ];
    }
}
