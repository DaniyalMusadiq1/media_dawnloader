<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiToken = $request->header('X-API-Token');

        if (!$apiToken) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'API token required'], 401);
        }

        $user = User::where('api_token', $apiToken)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Invalid API token'], 401);
        }

        // Attach user to request for later use
        $request->merge(['authenticated_user' => $user]);

        return $next($request);
    }
}
