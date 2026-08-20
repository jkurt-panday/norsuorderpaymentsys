<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FormInput;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display the client portal dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        // No user_id column on form_inputs (it's a public submission table,
        // not tied to auth accounts) — matching by email is the pragmatic
        // option that works today with the existing form_inputs_email_index,
        // no migration needed. Trade-off: if a client mistypes their email
        // on the public form, or submits under a different address, it
        // won't show up here. A user_id column would be the more robust
        // long-term fix if that turns out to matter in practice.
        $submissions = FormInput::query()
            ->where('email', $user->email)
            ->with(['membership', 'paymentDetailOption', 'staffInput'])
            ->latest()
            ->get();

        return Inertia::render('client/Dashboard', [
            'submissions' => $submissions,
        ]);
    }
}