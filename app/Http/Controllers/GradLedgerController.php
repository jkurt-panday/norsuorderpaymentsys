<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GradLedgerController extends Controller
{
    public function index(): Response
    {
        // We will start with empty data so you can build out your layout first!
        return Inertia::render('graduate-ledger/Index', [
            'records' => []
        ]);
    }
    }