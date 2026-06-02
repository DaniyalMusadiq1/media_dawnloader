<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExtensionController extends Controller
{
    /**
     * Submit URL for download from browser extension.
     */
    public function submit(Request $request): JsonResponse
    {
        // Authenticate via API token
        $apiToken = $request->header('X-API-Token');
        
        if (!$apiToken) {
            return response()->json(['error' => 'API token required'], 401);
        }

        $user = User::where('api_token', $apiToken)->first();
        
        if (!$user) {
            return response()->json(['error' => 'Invalid API token'], 401);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'type' => 'nullable|in:audio,video',
            'quality' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check rate limits for extension
        $todayStart = now()->startOfDay();
        $todayCount = Download::forUser($user->id)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $dailyLimit = $user->isPremium() ? 100 : 50;
        if ($todayCount >= $dailyLimit) {
            return response()->json([
                'error' => 'Daily limit exceeded',
                'message' => 'You have reached your daily download limit.',
            ], 429);
        }

        $download = Download::create([
            'user_id' => $user->id,
            'original_url' => $request->url,
            'title' => 'Processing...',
            'type' => $request->type ?? $user->preferences['default_format'] ?? 'video',
            'quality' => $request->quality ?? $user->preferences['default_quality'] ?? 'best',
            'status' => Download::STATUS_PENDING,
        ]);

        // Queue the download job
        \App\Jobs\ProcessDownloadJob::dispatch($download);

        return response()->json([
            'message' => 'Download queued',
            'download' => [
                'id' => $download->id,
                'url' => $download->original_url,
                'status' => $download->status,
            ],
        ], 201);
    }

    /**
     * Get extension status and user info.
     */
    public function status(Request $request): JsonResponse
    {
        $apiToken = $request->header('X-API-Token');
        
        if (!$apiToken) {
            return response()->json(['error' => 'API token required'], 401);
        }

        $user = User::where('api_token', $apiToken)->first();
        
        if (!$user) {
            return response()->json(['error' => 'Invalid API token'], 401);
        }

        $todayStart = now()->startOfDay();
        $todayCount = Download::forUser($user->id)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $pendingCount = Download::forUser($user->id)
            ->whereIn('status', [Download::STATUS_PENDING, Download::STATUS_PROCESSING])
            ->count();

        return response()->json([
            'user' => [
                'is_premium' => $user->isPremium(),
                'daily_limit' => $user->isPremium() ? 100 : 50,
                'downloads_today' => $todayCount,
                'remaining_today' => max(0, ($user->isPremium() ? 100 : 50) - $todayCount),
                'pending_downloads' => $pendingCount,
                'max_pending' => $user->isPremium() ? 50 : 10,
            ],
        ]);
    }
}
