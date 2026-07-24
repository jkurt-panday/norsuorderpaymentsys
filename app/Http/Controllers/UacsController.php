<?php

namespace App\Http\Controllers;

use App\Http\Requests\UacsRequest;
use App\Models\Uacs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UacsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $uacs = Uacs::orderBy('object_code')->paginate(10);
        return Inertia::render('staff/uacs/uacs', compact('uacs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('staff/uacs/createuacs');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UacsRequest $request)
    {
        try {
            DB::beginTransaction();

            Uacs::create($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create UACS: ' . $e->getMessage(), [
                'request' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create UACS record. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * Note: Variable left as $uacs to match Laravel's natural singular/plural
     * resource parameters for the word "uacs".
     */
    public function edit(Uacs $uacs)
    {
        return Inertia::render('staff/uacs/edituacs', compact('uacs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UacsRequest $request, Uacs $uacs)
    {
        try {
            DB::beginTransaction();

            $uacs->update($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update UACS ID {$uacs->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update UACS. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage safely.
     */
    public function destroy(Uacs $uacs)
    {
        try {
            DB::beginTransaction();

            // Safety check: Don't allow deletion if referenced by any staff inputs.
            // Using withTrashed() makes this check robust against soft-deletes.
            $hasRelations = $uacs->staffInputs()
                ->withTrashed()
                ->exists();

            if ($hasRelations) {
                return back()->with('error', 'Cannot delete UACS code that is currently in use.');
            }

            $uacs->delete();

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete UACS ID {$uacs->id}: " . $e->getMessage());

            return back()->with('error', 'Failed to delete UACS. Please check if it is still in use.');
        }
    }
}