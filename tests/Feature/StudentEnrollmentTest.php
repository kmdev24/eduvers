<?php

namespace Tests\Feature;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin enrolling students in a Senior High School strand or a College course.
 */
class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Section $ictSection;      // SHS · Grade 11 · ICT
    private Section $collegeSection;  // College · 1st Year · HRS
    private Subject $ictSubject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $term    = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $shs     = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $college = GradeLevel::create(['name' => '1st Year', 'level_type' => LevelType::College]);
        $ict     = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $hrs     = TrackStrand::create(['name' => 'HRS', 'category' => TrackCategory::College]);

        $this->ictSection     = Section::create(['name' => 'ICT 11-A', 'capacity' => 2, 'grade_level_id' => $shs->id, 'track_strand_id' => $ict->id]);
        $this->collegeSection = Section::create(['name' => 'HRS 1-A', 'capacity' => 40, 'grade_level_id' => $college->id, 'track_strand_id' => $hrs->id]);

        $this->ictSubject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $shs->id, 'track_strand_id' => $ict->id, 'academic_term_id' => $term->id,
        ]);
        Subject::create([
            'code' => 'HRS-FBS1', 'name' => 'Food and Beverage Services',
            'grade_level_id' => $college->id, 'track_strand_id' => $hrs->id, 'academic_term_id' => $term->id,
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    public function test_enrolling_a_student_in_a_strand_gives_them_the_whole_programme(): void
    {
        $student = User::factory()->student()->create(['name' => 'Kyle New']);

        $this->assertNull($student->section_id);
        $this->assertFalse($student->takesSubject($this->ictSubject->id));

        $this->actingAs($this->admin)->get(route('admin.student-enrollment.index'))
            ->assertOk()->assertSee('Kyle New')->assertSee('Not enrolled');

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->ictSection->id,
                'student_ids' => [$student->id],
            ])
            ->assertSessionHas('status');

        $student->refresh();
        $this->assertSame($this->ictSection->id, $student->section_id);
        $this->assertTrue($student->takesSubject($this->ictSubject->id), 'the strand’s subject came with the section');

        $this->actingAs($student)->get(route('student.subjects.index'))
            ->assertOk()->assertSee('Computer Programming 1');
    }

    public function test_several_students_are_enrolled_at_once_and_a_college_course_works_the_same(): void
    {
        $students = User::factory()->count(3)->student()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->collegeSection->id,
                'student_ids' => $students->pluck('id')->all(),
            ])
            ->assertSessionHas('status');

        foreach ($students as $student) {
            $this->assertSame($this->collegeSection->id, $student->fresh()->section_id);
        }
    }

    public function test_a_student_is_not_moved_into_another_year_level_by_accident(): void
    {
        // Already in 1st Year College
        $collegeStudent = User::factory()->student($this->collegeSection)->create(['name' => 'Kyle']);
        $newStudent     = User::factory()->student()->create(['name' => 'Fresh Start']);

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->ictSection->id,   // Grade 11, a different year level
                'student_ids' => [$collegeStudent->id, $newStudent->id],
            ])
            ->assertSessionHas('status');

        // The one with no year level went in; the College student was left where they were
        $this->assertSame($this->ictSection->id, $newStudent->fresh()->section_id);
        $this->assertSame($this->collegeSection->id, $collegeStudent->fresh()->section_id, 'year level unchanged');

        $this->assertStringContainsString('Kyle', session('status'));
        $this->assertStringContainsString('another year level', session('status'));
    }

    public function test_a_year_level_set_on_the_account_sticks_and_is_respected_without_a_section(): void
    {
        $college = $this->collegeSection->gradeLevel;
        $hrs     = $this->collegeSection->trackStrand;

        // Admitted into 1st Year HRS, no section chosen yet
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Kyle', 'email' => 'kyle@eduvers.test', 'role' => 'student',
            'grade_level_id' => $college->id, 'track_strand_id' => $hrs->id,
            'section_id' => '', 'password' => 'secret-pass-1',
        ])->assertSessionHasNoErrors();

        $kyle = User::where('email', 'kyle@eduvers.test')->sole();
        $this->assertNull($kyle->section_id);
        $this->assertSame($college->id, $kyle->grade_level_id, 'the year level stuck');
        $this->assertSame($hrs->id, $kyle->track_strand_id, 'the course stuck');

        // The Grade 11 section must now refuse him even though he has no section
        $this->actingAs($this->admin)->post(route('admin.student-enrollment.store'), [
            'section_id' => $this->ictSection->id, 'student_ids' => [$kyle->id],
        ])->assertSessionHas('error');

        $this->assertNull($kyle->fresh()->section_id);

        // A 1st Year section takes him, and the section then sets his strand
        $this->actingAs($this->admin)->post(route('admin.student-enrollment.store'), [
            'section_id' => $this->collegeSection->id, 'student_ids' => [$kyle->id],
        ])->assertSessionHas('status');

        $kyle->refresh();
        $this->assertSame($this->collegeSection->id, $kyle->section_id);
        $this->assertSame($this->collegeSection->track_strand_id, $kyle->track_strand_id);
    }

    public function test_the_course_is_guarded_as_well_as_the_year_level(): void
    {
        $bsit = TrackStrand::create(['name' => 'BSIT', 'category' => TrackCategory::College]);

        // 1st Year BSIT, no section. HRS 1-A is the same year level but a different course.
        $kyle = User::factory()->student()->create([
            'name'            => 'Kyle',
            'grade_level_id'  => $this->collegeSection->grade_level_id,
            'track_strand_id' => $bsit->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.student-enrollment.store'), [
            'section_id' => $this->collegeSection->id, 'student_ids' => [$kyle->id],
        ])->assertSessionHas('error');

        $this->assertNull($kyle->fresh()->section_id, 'a BSIT student does not land in HRS');

        // Their own course in their own year level does take them
        $bsitSection = Section::create([
            'name' => 'BSIT 1-A', 'capacity' => 40,
            'grade_level_id'  => $this->collegeSection->grade_level_id,
            'track_strand_id' => $bsit->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.student-enrollment.store'), [
            'section_id' => $bsitSection->id, 'student_ids' => [$kyle->id],
        ])->assertSessionHas('status');

        $this->assertSame($bsitSection->id, $kyle->fresh()->section_id);
    }

    public function test_a_section_decides_the_year_level_when_both_are_submitted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Mixed Up', 'email' => 'mixed@eduvers.test', 'role' => 'student',
            'grade_level_id' => $this->collegeSection->grade_level_id,   // says College
            'section_id' => $this->ictSection->id,                       // but the section is Grade 11
            'password' => 'secret-pass-1',
        ])->assertSessionHasNoErrors();

        $student = User::where('email', 'mixed@eduvers.test')->sole();
        $this->assertSame($this->ictSection->grade_level_id, $student->grade_level_id, 'the section wins');
        $this->assertSame($this->ictSection->track_strand_id, $student->track_strand_id);
    }

    public function test_a_college_course_cannot_be_set_at_a_senior_high_year_level(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Wrong Mix', 'email' => 'wrong@eduvers.test', 'role' => 'student',
            'grade_level_id'  => $this->ictSection->grade_level_id,        // Grade 11 (SHS)
            'track_strand_id' => $this->collegeSection->track_strand_id,   // HRS (College)
            'password' => 'secret-pass-1',
        ])->assertSessionHasErrors('track_strand_id');

        $this->assertDatabaseMissing('users', ['email' => 'wrong@eduvers.test']);
    }

    public function test_moving_only_students_from_another_year_level_changes_nothing(): void
    {
        $student = User::factory()->student($this->collegeSection)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->ictSection->id,
                'student_ids' => [$student->id],
            ])
            ->assertSessionHas('error');

        $this->assertSame($this->collegeSection->id, $student->fresh()->section_id);
    }

    public function test_a_student_still_moves_between_sections_of_the_same_year_level(): void
    {
        $other = Section::create([
            'name' => 'ICT 11-B', 'capacity' => 40,
            'grade_level_id' => $this->ictSection->grade_level_id,
            'track_strand_id' => $this->ictSection->track_strand_id,
        ]);

        $student = User::factory()->student($other)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->ictSection->id,
                'student_ids' => [$student->id],
            ])
            ->assertSessionHas('status');

        $this->assertSame($this->ictSection->id, $student->fresh()->section_id);
    }

    public function test_a_section_is_never_filled_past_its_capacity(): void
    {
        $students = User::factory()->count(3)->student()->create(); // capacity is 2

        $this->actingAs($this->admin)
            ->post(route('admin.student-enrollment.store'), [
                'section_id'  => $this->ictSection->id,
                'student_ids' => $students->pluck('id')->all(),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $this->ictSection->students()->count(), 'nobody was enrolled');
    }

    public function test_moving_a_student_drops_the_request_the_move_makes_pointless(): void
    {
        $teacher = User::factory()->teacher()->create();
        $class   = $this->ictSubject->assignTeacher($teacher, $this->ictSection);

        // A sibling section of the same year level and course, i.e. a plain transfer
        $sibling = Section::create([
            'name' => 'ICT 11-B', 'capacity' => 40,
            'grade_level_id'  => $this->ictSection->grade_level_id,
            'track_strand_id' => $this->ictSection->track_strand_id,
        ]);

        // Joined ICT 11-A's class from outside, then gets enrolled in that very section
        $student = User::factory()->student($sibling)->create();
        ClassEnrollment::request($student, $class, $this->admin);

        $this->actingAs($this->admin)->post(route('admin.student-enrollment.store'), [
            'section_id'  => $this->ictSection->id,
            'student_ids' => [$student->id],
        ])->assertSessionHas('status');

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_students_follow_their_section_when_its_year_level_or_course_changes(): void
    {
        $student = User::factory()->student()->create();
        $student->enrollInSection($this->ictSection);

        $this->ictSection->update([
            'grade_level_id'  => $this->collegeSection->grade_level_id,
            'track_strand_id' => $this->collegeSection->track_strand_id,
        ]);

        $student->refresh();
        $this->assertSame($this->collegeSection->grade_level_id, $student->grade_level_id);
        $this->assertSame($this->collegeSection->track_strand_id, $student->track_strand_id);
    }

    public function test_only_admins_can_open_or_use_the_page(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $this->get(route('admin.student-enrollment.index'))->assertRedirect(route('login'));
        $this->actingAs($teacher)->get(route('admin.student-enrollment.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.student-enrollment.index'))->assertForbidden();

        $this->actingAs($teacher)->post(route('admin.student-enrollment.store'), [
            'section_id' => $this->ictSection->id, 'student_ids' => [$student->id],
        ])->assertForbidden();

        $this->assertNull($student->fresh()->section_id);
    }
}
