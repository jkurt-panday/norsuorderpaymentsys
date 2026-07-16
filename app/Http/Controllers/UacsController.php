<?php

namespace App\Http\Controllers;

use App\Http\Requests\UacsRequest;
use App\Models\Uacs;
use Illuminate\Http\Request;

class UacsController extends Controller
{
    public function index()
    {
        $uacs = Uacs::orderBy('object_code')->paginate(10);
        return view('staff.uacs.index', compact('uacs'));
    }

    public function create()
    {
        return view('staff.uacs.create');
    }

    public function store(UacsRequest $request)
    {
        Uacs::create($request->validated());
        return redirect()->route('staff.uacs.index')
            ->with('success', 'UACS created successfully.');
    }

    public function edit(Uacs $uacs)
    {
        return view('staff.uacs.edit', compact('uacs'));
    }

    public function update(UacsRequest $request, Uacs $uacs)
    {
        $uacs->update($request->validated());
        return redirect()->route('staff.uacs.index')
            ->with('success', 'UACS updated successfully.');
    }

    public function destroy(Uacs $uacs)
    {
        if ($uacs->staffInputs()->exists()) {
            return back()->with('error', 'Cannot delete UACS that is in use.');
        }

        $uacs->delete();
        return redirect()->route('staff.uacs.index')
            ->with('success', 'UACS deleted successfully.');
    }
}