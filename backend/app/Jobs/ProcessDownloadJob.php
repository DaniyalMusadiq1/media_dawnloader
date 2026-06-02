<?php

namespace App\Jobs;

use App\Events\DownloadCompleted;
use App\Events\DownloadFailed;
use App\Events\DownloadProgress;
use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels, Queueable;

    /**
     * The download model instance.
     */
    public Download $download;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Backoff strategy in seconds.
     */
    public array $backoff = [5, 30, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if download was cancelled
        if ($this->download->status === Download::STATUS_CANCELLED) {
            return;
        }

        // Update status to processing
        $this->download->update([
            'status' => Download::STATUS_PROCESSING,
            'progress' => 0,
        ]);

        try {
            // Get yt-dlp path from config
            $ytDlpPath = config('app.yt_dlp_path', '/usr/local/bin/yt-dlp');
            
            // Determine output format based on type
            $formatArg = $this->download->type === 'audio' 
                ? '-x --audio-format mp3 --audio-quality 0' 
                : '-f bestvideo+bestaudio/best';

            // Quality argument
            $qualityArg = '';
            if ($this->download->quality && $this->download->quality !== 'best') {
                $qualityArg = $this->download->type === 'video' 
                    ? "-f best[height<={$this->download->quality}]" 
                    : "--audio-quality {$this->download->quality}";
            }

            // Output directory
            $outputDir = storage_path('app/downloads/' . $this->download->user_id);
            if (!Storage::disk('local')->exists('downloads/' . $this->download->user_id)) {
                Storage::disk('local')->makeDirectory('downloads/' . $this->download->user_id);
            }

            // Build command
            $outputTemplate = "$outputDir/%(title)s.%(ext)s";
            $command = sprintf(
                '%s %s %s --print-json --newline -o "%s" "%s"',
                escapeshellcmd($ytDlpPath),
                $formatArg,
                $qualityArg,
                $outputTemplate,
                escapeshellarg($this->download->original_url)
            );

            // Execute process
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(7200); // 2 hour timeout
            
            $process->run(function ($type, $buffer) {
                // Parse progress from output
                $this->parseProgress($buffer);
            });

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Download failed: ' . $process->getErrorOutput());
            }

            // Parse output to get file info
            $output = $process->getOutput();
            $this->processOutput($output);

            // Mark as completed
            $this->download->update([
                'status' => Download::STATUS_COMPLETED,
                'progress' => 100,
            ]);

            broadcast(new DownloadCompleted($this->download))->toOthers();

        } catch (\Exception $e) {
            Log::error('Download failed', [
                'download_id' => $this->download->id,
                'error' => $e->getMessage(),
            ]);

            $this->download->update([
                'status' => Download::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            broadcast(new DownloadFailed($this->download))->toOthers();

            throw $e; // Re-throw for retry mechanism
        }
    }

    /**
     * Parse progress from yt-dlp output.
     */
    private function parseProgress(string $buffer): void
    {
        // Match patterns like: [download] 45.2% of 100.00MiB at 1.50MiB/s ETA 00:36
        if (preg_match('/\[download\]\s+(\d+\.\d+)%.*?at\s+([\d\.]+\w+\/s).*?ETA\s+(\d+:\d+)/', $buffer, $matches)) {
            $progress = (int) floatval($matches[1]);
            $speed = $matches[2];
            $eta = $this->parseTimeToSeconds($matches[3]);

            $this->download->updateProgress($progress, $speed, $eta);
        }
    }

    /**
     * Process yt-dlp JSON output.
     */
    private function processOutput(string $output): void
    {
        // Find JSON output from yt-dlp
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $data = json_decode(trim($line), true);
            if ($data && isset($data['title'])) {
                $this->download->update([
                    'title' => $data['title'] ?? $this->download->title,
                    'file_size' => $data['filesize'] ?? null,
                    'duration' => $data['duration'] ?? null,
                    'thumbnail' => $data['thumbnail'] ?? null,
                ]);
                
                // Try to find the downloaded file
                if (isset($data['_filename'])) {
                    $relativePath = str_replace(storage_path('app/'), '', $data['_filename']);
                    $this->download->update([
                        'file_path' => $relativePath,
                        'download_url' => Storage::url($relativePath),
                    ]);
                }
                break;
            }
        }
    }

    /**
     * Parse time string (MM:SS or HH:MM:SS) to seconds.
     */
    private function parseTimeToSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        
        if (count($parts) === 2) {
            return $parts[0] * 60 + $parts[1];
        }
        
        if (count($parts) === 3) {
            return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        }

        return 0;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Download job failed permanently', [
            'download_id' => $this->download->id,
            'error' => $exception->getMessage(),
        ]);

        $this->download->update([
            'status' => Download::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
