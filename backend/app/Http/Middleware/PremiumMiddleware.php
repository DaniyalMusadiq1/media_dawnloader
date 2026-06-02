<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PremiumMiddleware
{
    /**
     * Handle an incoming request - check if user is premium.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->get('authenticated_user');

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user->isPremium()) {
            return response()->json([
                'error' => 'Payment Required',
                'message' => 'This feature requires a premium subscription',
                'upgrade_url' => '/settings#upgrade',
            ], 402);
        }

        return $next($request);
    }
}
