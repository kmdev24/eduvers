<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use App\Notifications\EnrollmentDecisionNotification;
use App\Notifications\NewLessonNotification;
use App\Support\Gradebook;
use App\Support\StudentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Joining a class outside the student's own section: asked by a teacher or the
 * student, and only effective once an admin approves it.
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;
    private Section $sectionA;
    private Section $sectionB;
    private User $teacher;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $term   = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level  = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);

        // A section takes every subject of its own strand automatically, so the section a
        // student is added *from* has to be another strand of the same year level.
        $otherStrand = TrackStrand::create(['name' => 'ABM', 'category' => TrackCategory::Academic]);

        $this->sectionA = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);
        $this->sectionB = Section::create(['name' => 'ABM 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $otherStrand->id]);

        $this->subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        $this->teacher = User::factory()->teacher()->create();
        $this->admin   = User::factory()->admin()->create();
        $this->subject->assignTeacher($this->teacher, $this->sectionA); // ICT 11-A's class gets a teacher
    }

    public function test_a_teachers_request_only_gives_access_after_an_admin_approves_it(): void
    {
        // Not yet admitted into a year level or course, so the class is open to them
        $student = User::factory()->student()->create();
        $class   = $this->teacher->teachingAssignments()->first();
        $lesson  = $this->lesson();
        $quiz    = $this->publishedQuiz();

        $this->actingAs($this->teacher)
            ->post(route('teacher.enrollments.store'), ['class_id' => $class->id, 'student_id' => $student->id])
            ->assertSessionHas('status');

        $enrollment = ClassEnrollment::sole();
        $this->assertSame(EnrollmentStatus::Pending, $enrollment->status);

        // Waiting: still no access
        $this->actingAs($student)->get(route('student.subjects.show', $this->subject))->assertForbidden();
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertForbidden();
        $this->actingAs($student)->get(route('student.quizzes.show', $quiz))->assertForbidden();

        // Students and teachers can't approve
        $this->actingAs($student)->patch(route('admin.enrollments.update', $enrollment), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->teacher)->patch(route('admin.enrollments.update', $enrollment), ['status' => 'approved'])->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('admin.enrollments.update', $enrollment), ['status' => 'approved'])
            ->assertSessionHas('status');

        $this->actingAs($student)->get(route('student.subjects.show', $this->subject))->assertOk()->assertSee($quiz->title);
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertOk();
        $this->actingAs($student)->get(route('student.quizzes.show', $quiz))->assertOk();
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        // The teacher sees the student in the class gradebook
        $this->assertTrue(Gradebook::build($this->teacher, null, $class->id)['students']->contains($student));

        // Removing the student takes the access away again
        $this->actingAs($this->admin)->patch(route('admin.enrollments.update', $enrollment), ['status' => 'rejected']);
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertForbidden();

        // ... and the teacher can ask again after a rejection
        $this->actingAs($this->teacher)
            ->post(route('teacher.enrollments.store'), ['class_id' => $class->id, 'student_id' => $student->id])
            ->assertSessionHas('status');

        $this->assertSame(EnrollmentStatus::Pending, $enrollment->fresh()->status);
    }

    public function test_a_teacher_can_only_add_students_to_their_own_classes(): void
    {
        $other   = User::factory()->teacher()->create();
        $student = User::factory()->student($this->sectionB)->create();
        $class   = $this->teacher->teachingAssignments()->first();

        $this->actingAs($other)
            ->post(route('teacher.enrollments.store'), ['class_id' => $class->id, 'student_id' => $student->id])
            ->assertNotFound();

        // ... and only students of the class's own year level
        $nextLevel   = GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);
        $nextSection = Section::create(['name' => 'ICT 12-A', 'capacity' => 40, 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->sectionA->track_strand_id]);
        $senior      = User::factory()->student($nextSection)->create();

        $this->actingAs($this->teacher)
            ->post(route('teacher.enrollments.store'), ['class_id' => $class->id, 'student_id' => $senior->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_an_admin_adds_a_student_straight_into_a_class_without_an_approval_step(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create(['name' => 'Ben Joined']);
        $class   = $this->teacher->teachingAssignments()->first();
        $lesson  = $this->lesson();

        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.class-students.store'), ['class_id' => $class->id, 'student_id' => $student->id])
            ->assertSessionHas('status');

        $enrollment = ClassEnrollment::sole();
        $this->assertSame(EnrollmentStatus::Approved, $enrollment->status, 'approved in one step');
        $this->assertSame($this->admin->id, $enrollment->decided_by);

        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertOk();
        Notification::assertSentTo($student, EnrollmentDecisionNotification::class);

        // Adding the same student again is refused, not duplicated
        $this->actingAs($this->admin)
            ->post(route('admin.class-students.store'), ['class_id' => $class->id, 'student_id' => $student->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('class_enrollments', 1);
    }

    public function test_a_class_only_takes_students_of_its_own_year_level_and_course(): void
    {
        $class = $this->teacher->teachingAssignments()->first(); // ICT 11-A, Grade 11 · ICT

        $otherCourse = User::factory()->student($this->sectionB)->create(['name' => 'Abby ABM']); // Grade 11 · ABM
        $otherLevel  = User::factory()->student(Section::create([
            'name' => 'ICT 12-A', 'capacity' => 40,
            'grade_level_id'  => GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs])->id,
            'track_strand_id' => $this->sectionA->track_strand_id,
        ]))->create(['name' => 'Cara Senior']);

        $this->actingAs($this->teacher)->get(route('teacher.enrollments.index', ['class' => $class->id]))
            ->assertOk()
            ->assertSee('Abby ABM')->assertSee('Not in ICT')
            ->assertSee('Cara Senior')->assertSee('Not in Grade 11');

        foreach ([$otherCourse, $otherLevel] as $student) {
            $this->actingAs($this->teacher)
                ->post(route('teacher.enrollments.store'), ['class_id' => $class->id, 'student_id' => $student->id])
                ->assertSessionHas('error');
        }

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_the_roster_lists_every_student_with_the_reason_they_cannot_be_added(): void
    {
        $inClass  = User::factory()->student($this->sectionA)->create(['name' => 'Ana In-Section']);
        $addable  = User::factory()->student()->create(['name' => 'Ben Addable']);
        $class    = $this->teacher->teachingAssignments()->first();

        $nextLevel   = GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);
        $nextSection = Section::create(['name' => 'ICT 12-A', 'capacity' => 40, 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->sectionA->track_strand_id]);
        $senior      = User::factory()->student($nextSection)->create(['name' => 'Cara Senior']);

        // No search term: everyone is listed, with a reason where they cannot be added
        $this->actingAs($this->teacher)->get(route('teacher.enrollments.index', ['class' => $class->id]))
            ->assertOk()
            ->assertSee('Ana In-Section')->assertSee('Already in this class')
            ->assertSee('Ben Addable')
            ->assertSee('Cara Senior')->assertSee('Not in Grade 11');

        // The search box narrows the same list
        $this->actingAs($this->teacher)->get(route('teacher.enrollments.index', ['class' => $class->id, 'q' => 'Ben']))
            ->assertOk()->assertSee('Ben Addable')->assertDontSee('Ana In-Section');

        // A waiting request replaces the Add button too
        ClassEnrollment::request($addable, $class, $this->teacher);
        $this->actingAs($this->teacher)->get(route('teacher.enrollments.index', ['class' => $class->id, 'q' => 'Ben']))
            ->assertOk()->assertSee('Waiting for approval');
    }

    public function test_a_students_section_enrolls_them_in_its_subjects_with_no_one_lifting_a_finger(): void
    {
        $student = User::factory()->student($this->sectionB)->create(); // ABM 11-A

        // Created after the section, and with no teacher assigned to anything
        $ownStrand = Subject::create([
            'code' => 'ABM-ACC1', 'name' => 'Accounting 1', 'grade_level_id' => $this->subject->grade_level_id,
            'track_strand_id' => $this->sectionB->track_strand_id, 'academic_term_id' => $this->subject->academic_term_id,
        ]);
        $core = Subject::create([
            'code' => 'CORE-PE1', 'name' => 'Physical Education 1', 'grade_level_id' => $this->subject->grade_level_id,
            'track_strand_id' => null, 'academic_term_id' => $this->subject->academic_term_id,
        ]);

        $this->assertTrue($student->takesSubject($ownStrand->id), 'their strand’s subject');
        $this->assertTrue($student->takesSubject($core->id), 'a core subject');
        $this->assertFalse($student->takesSubject($this->subject->id), 'another strand’s subject');

        $this->actingAs($student)->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee('Accounting 1')
            ->assertSee('Physical Education 1')
            ->assertSee('No teacher yet')
            ->assertDontSee('Computer Programming 1');

        // The core subject reaches the other strand's sections too
        $this->assertTrue(User::factory()->student($this->sectionA)->create()->takesSubject($core->id));
    }

    public function test_students_cannot_apply_for_a_class_of_another_strand_or_year_level(): void
    {
        $student = User::factory()->student($this->sectionB)->create(); // ABM 11-A
        $ictClass = $this->teacher->teachingAssignments()->first();     // ICT 11-A

        $nextLevel   = GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);
        $nextSection = Section::create(['name' => 'ABM 12-A', 'capacity' => 40, 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->sectionB->track_strand_id]);
        $nextSubject = Subject::create(['code' => 'ABM-ACC2', 'name' => 'Accounting 2', 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->sectionB->track_strand_id, 'academic_term_id' => $this->subject->academic_term_id]);
        $nextClass   = $nextSubject->assignTeacher(User::factory()->teacher()->create(), $nextSection);

        // Their own course is already in their list, so there is nothing left to apply for
        $this->actingAs($student)->get(route('student.enrollments.index'))
            ->assertOk()
            ->assertDontSee('Computer Programming 1')
            ->assertDontSee('Accounting 2');

        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $ictClass->id])->assertNotFound();
        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $nextClass->id])->assertNotFound();

        $this->assertDatabaseCount('class_enrollments', 0);
    }

    public function test_approved_students_are_notified_about_new_lessons(): void
    {
        Notification::fake();

        $joined   = User::factory()->student($this->sectionB)->create();
        $waiting  = User::factory()->student($this->sectionB)->create();
        $inClass  = User::factory()->student($this->sectionA)->create();
        $class    = $this->teacher->teachingAssignments()->first();

        ClassEnrollment::request($joined, $class, $this->teacher)->decide(EnrollmentStatus::Approved, $this->admin);
        ClassEnrollment::request($waiting, $class, $this->teacher);

        $this->assertSame(2, app(StudentNotifier::class)->lessonPosted($this->lesson()));

        Notification::assertSentTo([$joined, $inClass], NewLessonNotification::class);
        Notification::assertNotSentTo($waiting, NewLessonNotification::class);
    }

    public function test_the_student_is_notified_of_the_decision(): void
    {
        Notification::fake();

        $student = User::factory()->student($this->sectionB)->create();
        $class   = $this->teacher->teachingAssignments()->first();
        $request = ClassEnrollment::request($student, $class, $student);

        $this->actingAs($this->admin)->patch(route('admin.enrollments.update', $request), ['status' => 'approved']);

        Notification::assertSentTo($student, EnrollmentDecisionNotification::class, function (EnrollmentDecisionNotification $n, array $channels) {
            return $n->data['headline'] === 'Enrollment approved'
                && $n->url() === route('student.subjects.show', $this->subject)
                && in_array('database', $channels, true);
        });

        // Clicking Approve again decides nothing and does not notify twice
        $this->actingAs($this->admin)->patch(route('admin.enrollments.update', $request), ['status' => 'approved']);
        Notification::assertCount(1);
    }

    public function test_joined_students_count_on_the_teacher_dashboard_and_master_list(): void
    {
        User::factory()->student($this->sectionA)->create(['name' => 'Ana In-Section']);
        $joined = User::factory()->student($this->sectionB)->create(['name' => 'Ben Joined']);
        $class  = $this->teacher->teachingAssignments()->first();
        ClassEnrollment::request($joined, $class, $this->teacher)->decide(EnrollmentStatus::Approved, $this->admin);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['students'] === 2)
            ->assertViewHas('studentCounts', fn ($counts) => $counts[$class->id] === 2);

        $this->actingAs($this->teacher)->get(route('sections.masterlist', $this->sectionA))
            ->assertOk()->assertSee('Ana In-Section')->assertSee('Ben Joined')->assertSee('Joined from other sections');

        $csv = $this->actingAs($this->teacher)->get(route('sections.masterlist', [$this->sectionA, 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Ben Joined', $csv);
        $this->assertStringContainsString('ICT-PROG1', $csv);

        // Moving the student into the section drops the now-pointless request, so nobody is counted twice
        $this->actingAs($this->admin)->put(route('admin.users.update', $joined), [
            'name' => $joined->name, 'email' => $joined->email, 'role' => UserRole::Student->value, 'section_id' => $this->sectionA->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_enrollments', 0);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertViewHas('stats', fn (array $stats) => $stats['students'] === 2)
            ->assertViewHas('studentCounts', fn ($counts) => $counts[$class->id] === 2);

        $csv = $this->actingAs($this->teacher)->get(route('sections.masterlist', [$this->sectionA, 'format' => 'csv']))->streamedContent();
        $this->assertSame(1, substr_count($csv, 'Ben Joined'));
    }

    /* ------------------------------------------------------------------ */

    private function lesson(): Lesson
    {
        return Lesson::create([
            'title' => 'Variables', 'content' => 'Hello', 'subject_id' => $this->subject->id,
            'academic_term_id' => $this->subject->academic_term_id, 'teacher_id' => $this->teacher->id,
        ]);
    }

    /** Published to section A, where the class is taught. */
    private function publishedQuiz(): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Variables quiz', 'subject_id' => $this->subject->id, 'academic_term_id' => $this->subject->academic_term_id,
            'teacher_id' => $this->teacher->id, 'passing_score' => 75, 'is_published' => true, 'published_at' => now(),
        ]);
        $quiz->questions()->create(['question' => 'Stores a value?', 'options_json' => ['A' => 'Loop', 'B' => 'Variable'], 'correct_answer' => 'B']);
        $quiz->sections()->sync([$this->sectionA->id]);

        return $quiz;
    }
}
