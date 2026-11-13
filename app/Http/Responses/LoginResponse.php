<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();

        // SPA / API clients: return JSON, never redirect.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Authenticated.',
                'data'    => [
                    'id'    => $user?->id,
                    'name'  => $user?->name,
                    'email' => $user?->email,
                    'role'  => $user?->role,
                ],
            ]);
        }

        // Legacy Inertia web flow: role-based redirect.
        if ($user?->role === 'admin') {
            session()->flash('success', 'Welcome back, '.$user->name.'!');
            return redirect('/admin/dashboard');
        }

        if ($user?->role === 'staff') {
            session()->flash('success', 'Welcome back, '.$user->name.'!');
            return redirect('/staff/staffdashboard');
        }

        if ($user?->role === 'cashier') {
            session()->flash('success', 'Welcome back, '.$user->name.'!');
            return redirect('/cashier/requests');
        }

        abort(403, 'Invalid user role.');
    }
}
