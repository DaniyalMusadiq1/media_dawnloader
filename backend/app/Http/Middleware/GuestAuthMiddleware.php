<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guestToken = $request->header('X-Guest-Token');

        if (!$guestToken) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Guest token required'], 401);
        }

        $user = User::findByGuestToken($guestToken);

        if (!$user) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Invalid guest token'], 401);
        }

        // Attach user to request for later use
        $request->merge(['authenticated_user' => $user]);

        return $next($request);
    }
}
