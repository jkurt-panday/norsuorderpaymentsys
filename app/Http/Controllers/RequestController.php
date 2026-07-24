<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /**
     * API endpoint for live table updates
     */
    public function apiIndex(Request $request)
    {
        $query = FormInput::with('staffInput', 'membership');

        // Search filter
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->status) {
            $query->whereHas('staffInput', function($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        // Date filters
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $formInputs = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($formInputs);
    }
}