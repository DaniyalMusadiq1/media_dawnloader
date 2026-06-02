<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupDownloadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'downloads:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Delete old download files based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting download cleanup...');

        // Get retention days from config
        $freeRetentionDays = config('app.file_retention_days_free', 7);
        $premiumRetentionDays = config('app.file_retention_days_premium', 30);

        $deletedCount = 0;
        $deletedSize = 0;

        // Find downloads older than retention period
        $downloads = Download::whereNotNull('file_path')
            ->where('status', Download::STATUS_COMPLETED)
            ->where('created_at', '<', now()->subDays($freeRetentionDays))
            ->get();

        foreach ($downloads as $download) {
            $user = $download->user;
            
            // Check if user is premium (longer retention)
            $retentionDays = $user && $user->isPremium() ? $premiumRetentionDays : $freeRetentionDays;
            
            if ($download->created_at < now()->subDays($retentionDays)) {
                // Delete file
                if ($download->file_path && Storage::exists($download->file_path)) {
                    $fileSize = Storage::size($download->file_path);
                    Storage::delete($download->file_path);
                    $deletedSize += $fileSize;
                }

                // Clear file path from database but keep record
                $download->update([
                    'file_path' => null,
                    'download_url' => null,
                    'status' => Download::STATUS_CANCELLED, // Mark as expired
                ]);

                $deletedCount++;
                $this->line("Deleted: {$download->title}");
            }
        }

        $this->info("Cleanup complete!");
        $this->info("Deleted {$deletedCount} files");
        $this->info("Freed " . $this->formatBytes($deletedSize));

        return Command::SUCCESS;
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2) . ' ' . $units[$unit];
    }
}
