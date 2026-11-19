<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\PasswordResetResponse as Contract;

class PasswordResetResponse implements Contract
{
    /** @param  string  $status */
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Password reset successfully.',
            ]);
        }

        return redirect()->route('login')->with('status', trans($this->status));
    }
}
