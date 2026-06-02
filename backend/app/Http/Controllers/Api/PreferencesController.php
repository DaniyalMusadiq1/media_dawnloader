<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class PreferencesController extends Controller
{
    /**
     * Update user preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'default_format' => 'nullable|in:audio,video',
            'default_quality' => 'nullable|string',
            'timezone' => 'nullable|timezone',
            'auto_download' => 'nullable|boolean',
            'notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $preferences = array_merge(
            $user->preferences ?? [],
            $request->only([
                'default_format',
                'default_quality',
                'timezone',
                'auto_download',
                'notifications',
            ])
        );

        $user->update(['preferences' => $preferences]);

        return response()->json([
            'message' => 'Preferences updated',
            'preferences' => $user->preferences,
        ]);
    }

    /**
     * Get user preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'preferences' => $user->preferences,
        ]);
    }

    /**
     * Generate API token for browser extension.
     */
    public function generateApiToken(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = $user->generateApiToken();

        return response()->json([
            'message' => 'API token generated',
            'api_token' => $token,
            'warning' => 'Keep this token secure. It will not be shown again.',
        ]);
    }

    /**
     * Revoke API token.
     */
    public function revokeApiToken(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user->update(['api_token' => null]);

        return response()->json([
            'message' => 'API token revoked',
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
