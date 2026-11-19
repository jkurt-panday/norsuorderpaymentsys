<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        // SPA / API clients: 204 No Content.
        if ($request->wantsJson()) {
            return response()->noContent();
        }

        // Legacy Inertia web flow.
        return redirect('/');
    }
}
