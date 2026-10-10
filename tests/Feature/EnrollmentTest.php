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
 * student, and only effective once a developer approves it.
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;
    private Section $sectionA;
    private Section $sectionB;
    private User $teacher;
    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $term   = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level  = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);

        $this->sectionA = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);
        $this->sectionB = Section::create(['name' => 'ICT 11-B', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $this->subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        $this->teacher   = User::factory()->teacher()->create();
        $this->developer = User::factory()->developer()->create();
        $this->subject->assignTeacher($this->teacher, $this->sectionA); // the class is taught in section A only
    }

    public function test_a_teachers_request_only_gives_access_after_a_developer_approves_it(): void
    {
        $student = User::factory()->student($this->sectionB)->create();
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
        $this->actingAs($student)->patch(route('developer.enrollments.update', $enrollment), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->teacher)->patch(route('developer.enrollments.update', $enrollment), ['status' => 'approved'])->assertForbidden();

        $this->actingAs($this->developer)
            ->patch(route('developer.enrollments.update', $enrollment), ['status' => 'approved'])
            ->assertSessionHas('status');

        $this->actingAs($student)->get(route('student.subjects.show', $this->subject))->assertOk()->assertSee($quiz->title);
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertOk();
        $this->actingAs($student)->get(route('student.quizzes.show', $quiz))->assertOk();
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        // The teacher sees the student in the class gradebook
        $this->assertTrue(Gradebook::build($this->teacher, null, $class->id)['students']->contains($student));

        // Removing the student takes the access away again
        $this->actingAs($this->developer)->patch(route('developer.enrollments.update', $enrollment), ['status' => 'rejected']);
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertForbidden();
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

    public function test_students_apply_only_for_classes_of_their_course_and_can_apply_again_after_a_rejection(): void
    {
        $student = User::factory()->student($this->sectionB)->create();
        $class   = $this->teacher->teachingAssignments()->first();

        // A class of another strand is not open to this student
        $otherStrand  = TrackStrand::create(['name' => 'ABM', 'category' => TrackCategory::Academic]);
        $otherSection = Section::create(['name' => 'ABM 11-A', 'capacity' => 40, 'grade_level_id' => $this->subject->grade_level_id, 'track_strand_id' => $otherStrand->id]);
        $otherSubject = Subject::create(['code' => 'ABM-ACC1', 'name' => 'Accounting 1', 'grade_level_id' => $this->subject->grade_level_id, 'track_strand_id' => $otherStrand->id, 'academic_term_id' => $this->subject->academic_term_id]);
        $otherClass   = $otherSubject->assignTeacher(User::factory()->teacher()->create(), $otherSection);

        // Neither is a class of the same strand taught in another year level
        $nextLevel   = GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);
        $nextSection = Section::create(['name' => 'ICT 12-A', 'capacity' => 40, 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->subject->track_strand_id]);
        $nextSubject = Subject::create(['code' => 'ICT-PROG2', 'name' => 'Computer Programming 2', 'grade_level_id' => $nextLevel->id, 'track_strand_id' => $this->subject->track_strand_id, 'academic_term_id' => $this->subject->academic_term_id]);
        $nextClass   = $nextSubject->assignTeacher(User::factory()->teacher()->create(), $nextSection);

        $this->actingAs($student)->get(route('student.enrollments.index'))
            ->assertOk()->assertSee('Computer Programming 1')
            ->assertDontSee('Accounting 1')->assertDontSee('Computer Programming 2');

        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $otherClass->id])->assertNotFound();
        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $nextClass->id])->assertNotFound();

        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $class->id])->assertSessionHas('status');
        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $class->id])->assertSessionHas('error'); // already waiting

        $enrollment = ClassEnrollment::sole();
        $this->actingAs($this->developer)->patch(route('developer.enrollments.update', $enrollment), ['status' => 'rejected']);
        $this->actingAs($student)->get(route('student.subjects.show', $this->subject))->assertForbidden();

        $this->actingAs($student)->post(route('student.enrollments.store'), ['class_id' => $class->id])->assertSessionHas('status');
        $this->assertSame(EnrollmentStatus::Pending, $enrollment->fresh()->status);
    }

    public function test_approved_students_are_notified_about_new_lessons(): void
    {
        Notification::fake();

        $joined   = User::factory()->student($this->sectionB)->create();
        $waiting  = User::factory()->student($this->sectionB)->create();
        $inClass  = User::factory()->student($this->sectionA)->create();
        $class    = $this->teacher->teachingAssignments()->first();

        ClassEnrollment::request($joined, $class, $this->teacher)->decide(EnrollmentStatus::Approved, $this->developer);
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

        $this->actingAs($this->developer)->patch(route('developer.enrollments.update', $request), ['status' => 'approved']);

        Notification::assertSentTo($student, EnrollmentDecisionNotification::class, function (EnrollmentDecisionNotification $n, array $channels) {
            return $n->data['headline'] === 'Enrollment approved'
                && $n->url() === route('student.subjects.show', $this->subject)
                && in_array('database', $channels, true);
        });

        // Clicking Approve again decides nothing and does not notify twice
        $this->actingAs($this->developer)->patch(route('developer.enrollments.update', $request), ['status' => 'approved']);
        Notification::assertCount(1);
    }

    public function test_joined_students_count_on_the_teacher_dashboard_and_master_list(): void
    {
        User::factory()->student($this->sectionA)->create(['name' => 'Ana In-Section']);
        $joined = User::factory()->student($this->sectionB)->create(['name' => 'Ben Joined']);
        $class  = $this->teacher->teachingAssignments()->first();
        ClassEnrollment::request($joined, $class, $this->teacher)->decide(EnrollmentStatus::Approved, $this->developer);

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
        $this->actingAs($this->developer)->put(route('developer.users.update', $joined), [
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
