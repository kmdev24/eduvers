<?php

namespace App\Http\Controllers\Developer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assign a teacher to a subject for each section that takes it.
 */
class SubjectTeacherController extends Controller
{
    public function edit(Subject $subject): View
    {
        $subject->load(['gradeLevel', 'trackStrand', 'academicTerm']);

        return view('developer.subjects.teachers', [
            'subject'  => $subject,
            'sections' => $subject->eligibleSections()->with('trackStrand')->withCount('students')->orderBy('name')->get(),
            'assigned' => $subject->assignments()->pluck('teacher_id', 'section_id'), // [section_id => teacher_id]
            'teachers' => User::teachers()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $request->validate([
            'teachers'   => ['array'],
            'teachers.*' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)],
        ], [
            'teachers.*.exists' => 'Please choose a valid teacher.',
        ]);

        $sectionIds = $subject->eligibleSections()->pluck('id');

        DB::transaction(function () use ($request, $subject, $sectionIds) {
            foreach ($sectionIds as $sectionId) {
                $teacherId = $request->input("teachers.{$sectionId}");

                if (blank($teacherId)) {
                    $subject->assignments()->where('section_id', $sectionId)->delete();
                    continue;
                }

                SubjectTeacher::updateOrCreate(
                    ['subject_id' => $subject->id, 'section_id' => $sectionId],
                    ['teacher_id' => (int) $teacherId],
                );
            }
        });

        return redirect()->route('developer.subjects.index')
            ->with('status', "Teacher assignments for {$subject->code} were saved.");
    }
}
