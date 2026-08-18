<?php

namespace App\Http\Controllers;

use App\Models\Courses;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoursesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): InertiaResponse
    {
        $courses = Courses::orderBy('course_code', 'asc')->get();
        
        return Inertia::render('staff/courses/coursesIndex', [
            'courses' => $courses
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('staff/courses/createCourses');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): InertiaResponse
    {
        // 1. Validate the input fields
        $validated = $request->validate ([
            'course_code' => ['required', 'string', 'max:20', 'unique:courses,course_code'],
            'course_desc' => ['required', 'string', 'max:255'],
        ], [
            'course_code.required' => 'Please enter a course code.',
            'course_code.unique'   => 'This course code already exists.',
            'course_code.max'      => 'Course code must not exceed 20 characters.',
            'course_desc.required' => 'Please enter a course description or full name.',
            'course_desc.max'      => 'Course description must not exceed 255 characters.',
        ]);
    
        try {
            DB::beginTransaction();
    
            // 2. Create the record
            Courses::create([
                'course_code' => strtoupper($validated['course_code']), // Normalizes code to UPPERCASE (e.g., bsit -> BSIT)
                'course_desc' => $validated['course_desc'],
            ]);
    
            DB::commit();
    
            // 3. Return back to Inertia view with success state
            return redirect()->back()->with('success', 'Course created successfully!');
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            Log::error('Failed to create course: ' . $e->getMessage());
    
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create course. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Courses $courses)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Courses $courses): InertiaResponse
    {
        return Inertia::render('staff/courses/editCourses', [
            'courses' => $courses
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Courses $courses): InertiaResponse
    {
        // 1. Validate the input fields
        $validated = $request->validate([
            // Ignore current course ID when checking unique constraint
            'course_code' => ['required', 'string', 'max:20', 'unique:courses,course_code,' . $courses->id],
            'course_desc' => ['required', 'string', 'max:255'],
        ], [
            'course_code.required' => 'Please enter a course code.',
            'course_code.unique'   => 'This course code already exists.',
            'course_code.max'      => 'Course code must not exceed 20 characters.',
            'course_desc.required' => 'Please enter a course description.',
            'course_desc.max'      => 'Course description must not exceed 255 characters.',
        ]);
    
        try {
            DB::beginTransaction();
    
            // 2. Update record
            $courses->update([
                'course_code' => strtoupper($validated['course_code']),
                'course_desc' => $validated['course_desc'],
            ]);
    
            DB::commit();
    
            return redirect()->back()->with('success', 'Course updated successfully!');
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            Log::error('Failed to update course ID ' . $courses->id . ': ' . $e->getMessage());
    
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update course. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Courses $courses)
    {
        //
    }
}
