<?php

// app/Http/Controllers/CollegeOfficeController.php

namespace App\Http\Controllers;

use App\Models\CollegeOffice;
use Inertia\Response;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CollegeOfficeController extends Controller
{
    // ? used if you ever need a standalone list endpoint
    public function index(): Response
    {
        return Inertia::render('public/SubmitForm', [
            'collegeOffices' => CollegeOffice::orderBy('name')->get(['id', 'name']), // ? new
        ]);
    }

    // ? handles the "+" modal submission
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:college_offices,name'],
            ]);

            $collegeOffice = CollegeOffice::create($validated);

            return response()->json($collegeOffice, 201);
        } catch (QueryException $exception) {
            Log::error('Failed to create college or office.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to create the college or office at this time.',
            ], 500);
        }
    }
}
