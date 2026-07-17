<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipRequest;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $memberships = Membership::orderBy('member_code')->paginate(10);
        return view('staff.memberships.index', compact('memberships'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('staff.memberships.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MembershipRequest $request)
    {
        try {
            DB::beginTransaction();

            Membership::create($request->validated());

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create membership: ' . $e->getMessage(), [
                'request' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create membership. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Membership $membership)
    {
        return view('staff.memberships.show', compact('membership'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Membership $membership)
    {
        return view('staff.memberships.edit', compact('membership'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MembershipRequest $request, Membership $membership)
    {
        try {
            DB::beginTransaction();

            $membership->update($request->validated());

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update membership ID {$membership->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update membership. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage safely.
     */
    public function destroy(Membership $membership)
    {
        try {
            DB::beginTransaction();

            // Safety check: Don't allow deletion if referenced by any submissions.
            // Using withTrashed() makes this check robust against soft-deletes.
            $hasRelations = $membership->formInputs()
                ->withTrashed()
                ->exists();

            if ($hasRelations) {
                return back()->with('error', 'Cannot delete membership that has associated form inputs.');
            }

            $membership->delete();

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete membership ID {$membership->id}: " . $e->getMessage());

            return back()->with('error', 'Failed to delete membership. Please check if it is still in use.');
        }
    }
}