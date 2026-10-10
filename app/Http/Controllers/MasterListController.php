<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
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

        // Students from other sections a developer approved into one of this section's classes
        $joined = ClassEnrollment::approved()
            ->with(['student.section.gradeLevel', 'student.section.trackStrand', 'offering.subject'])
            ->whereHas('offering', fn ($q) => $q->where('section_id', $section->id))
            ->get()
            ->sortBy(fn (ClassEnrollment $e) => $e->student->name.' '.$e->offering->subject->code)
            ->values();

        if ($request->query('format') === 'csv') {
            $own = $students->values()->map(fn ($s) => [
                $s->name, $s->email, $section->name, $section->gradeLevel->name, $section->trackStrand->name, '',
            ]);
            $extra = $joined->map(fn (ClassEnrollment $e) => [
                $e->student->name, $e->student->email, $e->student->section?->name ?? '',
                $e->student->section?->gradeLevel?->name ?? '', $e->student->section?->trackStrand?->name ?? '', $e->offering->subject->code,
            ]);

            return Csv::download(
                Str::slug($section->name.' master list').'-'.now()->format('Y-m-d').'.csv',
                ['No.', 'Student name', 'Email', 'Section', 'Grade level', 'Track / strand', 'Joined class'],
                $own->concat($extra)->values()->map(fn ($row, $i) => [$i + 1, ...$row]),
            );
        }

        return view('reports.masterlist', [
            'section'  => $section,
            'students' => $students,
            'joined'   => $joined,
            'teachers' => SubjectTeacher::with(['subject', 'teacher'])
                ->where('section_id', $section->id)
                ->get()
                ->sortBy(fn ($c) => $c->subject->code)
                ->values(),
        ]);
    }
}
