<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'students' => User::students()->count(),
            'teachers' => User::teachers()->count(),
            'sections' => Section::count(),
            'subjects' => Subject::count(),
        ];

        // ['full_time' => 3, 'part_time' => 1, '' => 2 (unassigned)]
        $teacherTypes = User::teachers()
            ->selectRaw('teacher_type, COUNT(*) as total')
            ->groupBy('teacher_type')
            ->toBase()
            ->pluck('total', 'teacher_type');

        $sections = Section::with(['gradeLevel', 'trackStrand'])
            ->withCount('students')
            ->orderBy('name')
            ->take(6)
            ->get();

        $recentUsers = User::latest()->take(6)->get();

        return view('developer.dashboard', [
            'stats'        => $stats,
            'teacherTypes' => $teacherTypes,
            'sections'     => $sections,
            'recentUsers'  => $recentUsers,
            'currentTerm'  => AcademicTerm::getCurrent(),
        ]);
    }
}
