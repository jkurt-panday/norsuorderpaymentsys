<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as Contract;

class SuccessfulPasswordResetLinkRequestResponse implements Contract
{
    public function toResponse($request)
    {
        // Generic message — never confirm whether the address is registered.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'If that email address is registered, a password reset link has been sent.',
            ]);
        }

        return back()->with('status', trans(/** @var string */ $this->status ?? 'passwords.sent'));
    }
}
