<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShareController extends Controller
{
    /**
     * Get public share page data.
     */
    public function show(string $shareToken): JsonResponse
    {
        $download = Download::where('share_token', $shareToken)
            ->where('status', Download::STATUS_COMPLETED)
            ->first();

        if (!$download) {
            return response()->json(['error' => 'Download not found or not available'], 404);
        }

        return response()->json([
            'download' => [
                'id' => $download->id,
                'title' => $download->title,
                'type' => $download->type,
                'thumbnail' => $download->thumbnail,
                'duration' => $download->formatted_duration,
                'file_size' => $download->formatted_file_size,
                'created_at' => $download->created_at->toIso8601String(),
                'is_owner' => false, // Will be set by frontend if user owns it
            ],
            'stream_url' => $download->download_url,
            'can_download' => false, // Non-owners cannot download
        ]);
    }

    /**
     * Generate/update share token for a download.
     */
    public function generate(int $id, Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $download = Download::forUser($user->id)->find($id);

        if (!$download) {
            return response()->json(['error' => 'Download not found'], 404);
        }

        // Regenerate share token
        $download->update([
            'share_token' => \Illuminate\Support\Str::random(8),
        ]);

        return response()->json([
            'message' => 'Share link generated',
            'share_token' => $download->share_token,
            'share_url' => url("/share/{$download->share_token}"),
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
}
