<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCashier
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->role === UserRole::Cashier->value,
            Response::HTTP_FORBIDDEN,
            'Cashier privileges required.',
        );

        return $next($request);
    }
}
