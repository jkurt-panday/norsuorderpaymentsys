<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetResponse as Contract;

class FailedPasswordResetResponse implements Contract
{
    /** @param  string  $status */
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message'  => 'Validation failed.',
                'errors'   => ['email' => [trans($this->status)]],
            ], 422);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($this->status)]);
    }
}
