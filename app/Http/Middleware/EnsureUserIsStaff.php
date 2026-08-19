<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->user()?->role, ['staff', 'admin'])) {
            return $next($request);
        }

        if (! $request->user()) {
            return redirect()->route('login')->with('error', 'Please log in to access this area.');
        }

        return redirect()->route('client.dashboard')->with('error', 'Unauthorized access. Staff privileges required.');
    }
}
