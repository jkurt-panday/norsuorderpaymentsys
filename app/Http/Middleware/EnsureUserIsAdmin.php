<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if the user is authenticated (logged in)
        // 2. Check if their database 'role' column says exactly 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request); // Let them proceed to the route!
        }

        // 3. Kick them out with a 403 Forbidden error if they aren't an admin
        abort(403, 'Unauthorized action. Admin access required.');
    }
}
