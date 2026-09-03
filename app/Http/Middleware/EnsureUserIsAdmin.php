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
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request);
        }

        if (! $request->user()) {
            return redirect()->route('login')->with('error', 'Please log in to access this area.');
        }

        $redirectTo = match ($request->user()->role) {
            'staff' => route('staff.dashboard'),
            'cashier' => route('cashier.requests.index'),
            default => route('client.dashboard'),
        };

        return redirect($redirectTo)
            ->with('error', "You don't have permission to access that section.");
    }
}
