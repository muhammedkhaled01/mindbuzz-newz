<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Program;
use App\Models\School;
use App\Models\Stage;
use App\Models\TeacherProgram;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InstructorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with(['details.stage', 'teacher_programs.program'])
            ->where('role', '2');

        if ($request->filled('school')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('school_id', $request->input('school'));
            });
        }

        if ($request->filled('program')) {
            $query->whereHas('teacher_programs', function ($q) use ($request) {
                $q->where('program_id', $request->input('program'));
            });
        }

        if ($request->filled('grade')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('stage_id', $request->input('grade'));
            });
        }
        if ($request->filled('group')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('group_id', $request->input('group'));
            });
        }

        $instructors = $query->simplePaginate(10);

        $schools = School::all();
        $programs = Program::all();
        $grades = Stage::all();
        $classes = Group::all();

        return view('dashboard.instructors.index', compact('instructors', 'schools', 'programs', 'grades', 'classes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $schools = School::all();
        $programs = Program::all();
        $stages = Stage::all();
        $groups = Group::all();
        return view('dashboard.instructors.create', compact('schools', 'programs', 'stages', 'groups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:15',
            'password' => 'required|string|confirmed|min:6',
            'school_id' => 'required|exists:schools,id',
            'program_id' => 'required|exists:programs,id',
            'stage_id' => 'required|exists:stages,id',
            'group_id' => 'nullable|exists:groups,id',
            'parent_image' => 'nullable|image|max:2048'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'school_id' => $request->school_id,
            'role' => '2',
            'is_student' => 0
        ]);

        TeacherProgram::create([
            'teacher_id' => $user->id,
            'program_id' => $request->program_id,
            'grade_id' => $request->stage_id
        ]);

        UserDetail::create([
            'user_id' => $user->id,
            'school_id' => $request->school_id,
            'stage_id' => $request->stage_id
        ]);
        Group::create([

        ]);

        if ($request->hasFile('parent_image')) {
            $imagePath = $request->file('parent_image')->store('images', 'public');
            $user->parent_image = $imagePath;
            $user->save();
        }

        return redirect()->route('instructors.index')->with('success', 'Teacher created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $instructor = User::findOrFail($id);
        $schools = School::all();
        $programs = Program::all();
        $stages = Stage::all();
        $groups = Group::all();
        return view('dashboard.instructors.edit', compact('instructor', 'schools', 'programs', 'stages', 'groups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'required|string|max:15',
            'school_id' => 'required|exists:schools,id',
            'program_id' => 'required|exists:programs,id',
            'stage_id' => 'required|exists:stages,id',
            'group_id' => 'required|exists:groups,id',
            'parent_image' => 'nullable|image|max:2048'
        ]);

        $instructor = User::findOrFail($id);
        $instructor->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'school_id' => $request->school_id
        ]);

        TeacherProgram::where('teacher_id', $instructor->id)->update([
            'program_id' => $request->program_id,
            'grade_id' => $request->grade_id
        ]);

        UserDetail::where('user_id', $instructor->id)->update([
            'school_id' => $request->school_id,
            'stage_id' => $request->stage_id
        ]);


        if ($request->hasFile('parent_image')) {
            $imagePath = $request->file('parent_image')->store('images', 'public');
            $instructor->parent_image = $imagePath;
            $instructor->save();
        }

        return redirect()->route('instructors.index')->with('success', 'Teacher updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $instructor = User::findOrFail($id);

        $instructor->delete();

        return redirect()->route('instructors.index')->with('success', 'Teacher deleted successfully.');
    }
}
