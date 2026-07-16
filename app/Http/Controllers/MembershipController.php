<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipRequest;
use App\Models\Membership;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function index()
    {
        $memberships = Membership::orderBy('member_code')->paginate(10);
        return view('staff.memberships.index', compact('memberships'));
    }

    public function create()
    {
        return view('staff.memberships.create');
    }

    public function store(MembershipRequest $request)
    {
        Membership::create($request->validated());
        return redirect()->route('staff.memberships.index')
            ->with('success', 'Membership created successfully.');
    }

    public function show(Membership $membership)
    {
        return view('staff.memberships.show', compact('membership'));
    }

    public function edit(Membership $membership)
    {
        return view('staff.memberships.edit', compact('membership'));
    }

    public function update(MembershipRequest $request, Membership $membership)
    {
        $membership->update($request->validated());
        return redirect()->route('staff.memberships.index')
            ->with('success', 'Membership updated successfully.');
    }

    public function destroy(Membership $membership)
    {
        // Check if membership is in use
        if ($membership->formInputs()->exists()) {
            return back()->with('error', 'Cannot delete membership that has associated form inputs.');
        }

        $membership->delete();
        return redirect()->route('staff.memberships.index')
            ->with('success', 'Membership deleted successfully.');
    }
}