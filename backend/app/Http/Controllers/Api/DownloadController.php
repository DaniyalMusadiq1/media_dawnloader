<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\PlaylistDownload;
use App\Models\User;
use App\Jobs\ProcessDownloadJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    /**
     * Register a guest user and return token.
     */
    public function registerGuest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_token' => 'nullable|string|max:255|unique:users,guest_token',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'guest_token' => $request->guest_token ?? Str::random(32),
        ]);

        return response()->json([
            'guest_token' => $user->guest_token,
            'is_premium' => $user->is_premium,
            'preferences' => $user->preferences,
        ]);
    }

    /**
     * Submit a download request.
     */
    public function create(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'type' => 'nullable|in:audio,video',
            'quality' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check rate limits
        $pendingCount = Download::forUser($user->id)
            ->whereIn('status', [Download::STATUS_PENDING, Download::STATUS_PROCESSING])
            ->count();

        $maxPending = $user->isPremium() ? 50 : 10;
        if ($pendingCount >= $maxPending) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'You have reached your maximum pending downloads. Upgrade to premium for higher limits.',
            ], 429);
        }

        // Check for duplicate
        $existing = Download::where('original_url', $request->url)
            ->where('user_id', $user->id)
            ->where('status', Download::STATUS_COMPLETED)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Download already exists',
                'download' => $existing,
                'is_duplicate' => true,
            ]);
        }

        DB::beginTransaction();
        try {
            $download = Download::create([
                'user_id' => $user->id,
                'original_url' => $request->url,
                'title' => 'Processing...',
                'type' => $request->type ?? 'video',
                'quality' => $request->quality ?? 'best',
                'status' => $request->scheduled_at ? Download::STATUS_SCHEDULED : Download::STATUS_PENDING,
                'scheduled_at' => $request->scheduled_at ? now()->parse($request->scheduled_at) : null,
            ]);

            // Queue job with delay if scheduled
            if ($request->scheduled_at) {
                ProcessDownloadJob::dispatch($download)->delay(now()->parse($request->scheduled_at));
            } else {
                ProcessDownloadJob::dispatch($download);
            }

            DB::commit();

            return response()->json([
                'message' => 'Download queued successfully',
                'download' => $download,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create download'], 500);
        }
    }

    /**
     * Submit a playlist download.
     */
    public function createPlaylist(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'playlist_url' => 'required|url',
            'type' => 'nullable|in:audio,video',
            'quality' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // For now, create a placeholder playlist record
        // In production, you'd first fetch playlist info from yt-dlp
        $playlist = PlaylistDownload::create([
            'user_id' => $user->id,
            'playlist_url' => $request->playlist_url,
            'title' => 'Processing playlist...',
            'total_items' => 0,
            'status' => PlaylistDownload::STATUS_PENDING,
        ]);

        // Dispatch a job to fetch playlist info and queue individual downloads
        // ProcessPlaylistJob::dispatch($playlist, $request->only(['type', 'quality']));

        return response()->json([
            'message' => 'Playlist download queued',
            'playlist' => $playlist,
            'playlist_id' => $playlist->id,
        ], 201);
    }

    /**
     * Batch download (up to 10 URLs).
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'urls' => 'required|array|max:10',
            'urls.*' => 'required|url',
            'type' => 'nullable|in:audio,video',
            'quality' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $downloads = [];
        foreach ($request->urls as $url) {
            $download = Download::create([
                'user_id' => $user->id,
                'original_url' => $url,
                'title' => 'Processing...',
                'type' => $request->type ?? 'video',
                'quality' => $request->quality ?? 'best',
                'status' => Download::STATUS_PENDING,
            ]);

            ProcessDownloadJob::dispatch($download);
            $downloads[] = $download;
        }

        return response()->json([
            'message' => 'Batch download queued',
            'downloads' => $downloads,
            'count' => count($downloads),
        ], 201);
    }

    /**
     * Get all downloads for user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Download::with('playlistDownload')
            ->forUser($user->id)
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $downloads = $query->paginate($perPage);

        return response()->json([
            'downloads' => $downloads,
        ]);
    }

    /**
     * Get download status.
     */
    public function status(int $id): JsonResponse
    {
        $download = Download::find($id);

        if (!$download) {
            return response()->json(['error' => 'Download not found'], 404);
        }

        return response()->json([
            'download' => $download,
        ]);
    }

    /**
     * Delete a download.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $download = Download::forUser($user->id)->find($id);

        if (!$download) {
            return response()->json(['error' => 'Download not found'], 404);
        }

        // Cancel if processing
        if ($download->status === Download::STATUS_PROCESSING) {
            $download->update(['status' => Download::STATUS_CANCELLED]);
        }

        // Delete file if exists
        if ($download->file_path) {
            \Illuminate\Support\Facades\Storage::delete($download->file_path);
        }

        $download->delete();

        return response()->json(['message' => 'Download deleted']);
    }

    /**
     * Get download statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $total = Download::forUser($user->id)->count();
        $completed = Download::forUser($user->id)->where('status', Download::STATUS_COMPLETED)->count();
        $failed = Download::forUser($user->id)->where('status', Download::STATUS_FAILED)->count();
        $totalSize = Download::forUser($user->id)
            ->whereNotNull('file_size')
            ->sum('file_size');

        return response()->json([
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'total_size' => $totalSize,
            'formatted_total_size' => $this->formatBytes($totalSize),
        ]);
    }

    /**
     * Authenticate user from request.
     */
    private function authenticateUser(Request $request): ?User
    {
        $guestToken = $request->header('X-Guest-Token');
        
        if (!$guestToken) {
            return null;
        }

        return User::findByGuestToken($guestToken);
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
