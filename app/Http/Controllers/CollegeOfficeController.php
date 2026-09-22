<?php

// app/Http/Controllers/CollegeOfficeController.php

namespace App\Http\Controllers;

use App\Models\CollegeOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollegeOfficeController extends Controller
{
    // ? used if you ever need a standalone list endpoint
    public function index(): JsonResponse
    {
         return Inertia::render('public/SubmitForm', [
            'collegeOffices' => CollegeOffice::orderBy('name')->get(['id', 'name']), // ? new
        ]);
    }

    // ? handles the "+" modal submission
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:college_offices,name'],
        ]);

        $collegeOffice = CollegeOffice::create($validated);

        return response()->json($collegeOffice, 201);
    }
}