<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not authenticated, let the auth middleware handle it (or return 401)
        if (!$user) {
            return $next($request);
        }

        // Admin can do anything
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Whitelisted POST/PUT routes for 'user' role
        $whitelistedRoutes = [
            'api/logout',
            'api/user/profile',
            'api/user/password'
        ];

        if (in_array($request->path(), $whitelistedRoutes)) {
            return $next($request);
        }

        // For non-admin ('user'), only allow GET, HEAD, OPTIONS
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        if (!in_array($request->method(), $safeMethods)) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini (Read-Only Mode).'
            ], 403);
        }

        return $next($request);
    }
}
