<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as Contract;

class FailedPasswordResetLinkRequestResponse implements Contract
{
    /** @param  string  $status */
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        // Same generic message as success — prevents account enumeration.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'If that email address is registered, a password reset link has been sent.',
            ]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($this->status)]);
    }
}
