<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\SubjectTeacher;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Student master list for a section: printable page, or CSV with ?format=csv.
 * Developers can open any section; teachers only sections they teach.
 */
class MasterListController extends Controller
{
    public function __invoke(Request $request, Section $section)
    {
        $user = $request->user();

        abort_unless(
            $user->isDeveloper()
                || ($user->isTeacher() && $user->teachingAssignments()->where('section_id', $section->id)->exists()),
            403,
            'You can only view master lists for sections you teach.'
        );

        $section->load(['gradeLevel', 'trackStrand']);
        $students = $section->students()->orderBy('name')->get();

        if ($request->query('format') === 'csv') {
            return Csv::download(
                Str::slug($section->name.' master list').'-'.now()->format('Y-m-d').'.csv',
                ['No.', 'Student name', 'Email', 'Section', 'Grade level', 'Track / strand'],
                $students->values()->map(fn ($s, $i) => [
                    $i + 1, $s->name, $s->email, $section->name, $section->gradeLevel->name, $section->trackStrand->name,
                ]),
            );
        }

        return view('reports.masterlist', [
            'section'  => $section,
            'students' => $students,
            'teachers' => SubjectTeacher::with(['subject', 'teacher'])
                ->where('section_id', $section->id)
                ->get()
                ->sortBy(fn ($c) => $c->subject->code)
                ->values(),
        ]);
    }
}
