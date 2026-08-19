<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
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
        return Inertia::render('client/Dashboard');
    }
}
