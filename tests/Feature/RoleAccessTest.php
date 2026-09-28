<?php

namespace Tests\Feature;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies the role guards (role:developer / role:teacher / role:student)
 * and the ownership rules (policies) that protect EduVers.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Pages that belong to exactly one role: [route name, role that may open it].
     */
    public static function roleOnlyPages(): array
    {
        return [
            'developer dashboard' => ['developer.dashboard',      'developer'],
            'developer users'     => ['developer.users.index',    'developer'],
            'developer new user'  => ['developer.users.create',   'developer'],
            'developer sections'  => ['developer.sections.index', 'developer'],
            'developer subjects'  => ['developer.subjects.index', 'developer'],
            'developer terms'     => ['developer.terms.index',    'developer'],
            'developer tracks'    => ['developer.tracks.index',   'developer'],
            'teacher dashboard'   => ['teacher.dashboard',        'teacher'],
            'teacher lessons'     => ['teacher.lessons.index',    'teacher'],
            'teacher quizzes'     => ['teacher.quizzes.index',    'teacher'],
            'teacher gradebook'   => ['teacher.gradebook',        'teacher'],
            'student dashboard'   => ['student.dashboard',        'student'],
            'student subjects'    => ['student.subjects.index',   'student'],
            'student quizzes'     => ['student.quizzes.index',    'student'],
            'student grades'      => ['student.grades',           'student'],
        ];
    }

    #[DataProvider('roleOnlyPages')]
    public function test_guests_are_redirected_to_the_login_page(string $route, string $allowedRole): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    #[DataProvider('roleOnlyPages')]
    public function test_only_the_owning_role_can_open_the_page(string $route, string $allowedRole): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs($this->userWithRole($role))->get(route($route));

            if ($role->value === $allowedRole) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_dashboard_link_sends_each_role_to_its_own_dashboard(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('dashboard'))
                ->assertRedirect(route($role->dashboardRoute()));
        }
    }

    public function test_announcements_are_managed_by_developers_and_teachers_only(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Developer))->get(route('announcements.index'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Teacher))->get(route('announcements.index'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Student))->get(route('announcements.index'))->assertForbidden();
    }

    public function test_every_role_can_open_their_profile(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('profile.edit'))->assertOk();
        }
    }

    public function test_students_cannot_post_announcements(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Student))
            ->post(route('announcements.store'), ['title' => 'Hack', 'body' => 'x', 'audience' => 'everyone'])
            ->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_teachers_cannot_edit_another_teachers_lesson(): void
    {
        ['subject' => $subject, 'sectionA' => $sectionA] = $this->academicStructure();

        $author = User::factory()->teacher()->create();
        $other  = User::factory()->teacher()->create();
        $subject->assignTeacher($author, $sectionA);

        $lesson = Lesson::create([
            'title' => 'Intro', 'content' => 'Hello', 'subject_id' => $subject->id,
            'academic_term_id' => $subject->academic_term_id, 'teacher_id' => $author->id,
        ]);

        $this->actingAs($author)->get(route('teacher.lessons.edit', $lesson))->assertOk();
        $this->actingAs($other)->get(route('teacher.lessons.edit', $lesson))->assertForbidden();
        $this->actingAs($other)->delete(route('teacher.lessons.destroy', $lesson))->assertForbidden();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
    }

    public function test_teachers_cannot_edit_another_teachers_quiz(): void
    {
        ['subject' => $subject] = $this->academicStructure();
        $author = User::factory()->teacher()->create();
        $other  = User::factory()->teacher()->create();

        $quiz = $this->makeQuiz($subject, $author);

        $this->actingAs($author)->get(route('teacher.quizzes.edit', $quiz))->assertOk();
        $this->actingAs($other)->get(route('teacher.quizzes.edit', $quiz))->assertForbidden();
    }

    public function test_students_can_only_take_quizzes_published_to_their_section(): void
    {
        ['subject' => $subject, 'sectionA' => $sectionA, 'sectionB' => $sectionB] = $this->academicStructure();
        $teacher = User::factory()->teacher()->create();

        $quiz = $this->makeQuiz($subject, $teacher, published: true);
        $quiz->sections()->sync([$sectionA->id]);

        $inSection    = User::factory()->student($sectionA)->create();
        $otherSection = User::factory()->student($sectionB)->create();

        $this->actingAs($inSection)->get(route('student.quizzes.show', $quiz))->assertOk();
        $this->actingAs($otherSection)->get(route('student.quizzes.show', $quiz))->assertForbidden();
    }

    public function test_students_cannot_take_unpublished_quizzes(): void
    {
        ['subject' => $subject, 'sectionA' => $sectionA] = $this->academicStructure();
        $quiz = $this->makeQuiz($subject, User::factory()->teacher()->create(), published: false);
        $quiz->sections()->sync([$sectionA->id]);

        $this->actingAs(User::factory()->student($sectionA)->create())
            ->get(route('student.quizzes.show', $quiz))
            ->assertForbidden();
    }

    public function test_quiz_submission_is_scored_immediately_and_only_once(): void
    {
        ['subject' => $subject, 'sectionA' => $sectionA] = $this->academicStructure();
        $quiz = $this->makeQuiz($subject, User::factory()->teacher()->create(), published: true);
        $quiz->sections()->sync([$sectionA->id]);
        $question = $quiz->questions()->first();

        $student = User::factory()->student($sectionA)->create();

        $this->actingAs($student)
            ->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => 'B']])
            ->assertRedirect(route('student.quizzes.show', $quiz));

        $this->assertDatabaseHas('quiz_submissions', [
            'quiz_id' => $quiz->id, 'student_id' => $student->id, 'total_items' => 1, 'passed' => true,
        ]);

        // A second attempt is refused
        $this->actingAs($student)->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => 'A']]);
        $this->assertDatabaseCount('quiz_submissions', 1);
    }

    public function test_master_list_is_limited_to_developers_and_the_sections_teachers(): void
    {
        ['subject' => $subject, 'sectionA' => $sectionA, 'sectionB' => $sectionB] = $this->academicStructure();
        $teacher = User::factory()->teacher()->create();
        $subject->assignTeacher($teacher, $sectionA);

        $this->actingAs($this->userWithRole(UserRole::Developer))->get(route('sections.masterlist', $sectionB))->assertOk();
        $this->actingAs($teacher)->get(route('sections.masterlist', $sectionA))->assertOk();
        $this->actingAs($teacher)->get(route('sections.masterlist', $sectionB))->assertForbidden();
        $this->actingAs(User::factory()->student($sectionA)->create())->get(route('sections.masterlist', $sectionA))->assertForbidden();
    }

    public function test_gradebook_export_is_for_teachers_only(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Student))->get(route('teacher.gradebook.export'))->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::Developer))->get(route('teacher.gradebook.export'))->assertForbidden();
    }

    /* ------------------------------------------------------------------ */

    private function userWithRole(UserRole $role): User
    {
        return match ($role) {
            UserRole::Developer => User::factory()->developer()->create(),
            UserRole::Teacher   => User::factory()->teacher()->create(),
            UserRole::Student   => User::factory()->student()->create(),
        };
    }

    /** One term, Grade 11, ICT strand, two sections and one ICT subject. */
    private function academicStructure(): array
    {
        $term   = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level  = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);

        $sectionA = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);
        $sectionB = Section::create(['name' => 'ICT 11-B', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        return compact('term', 'level', 'strand', 'sectionA', 'sectionB', 'subject');
    }

    /** A one-question quiz whose correct answer is "B". */
    private function makeQuiz(Subject $subject, User $teacher, bool $published = false): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Quiz 1', 'subject_id' => $subject->id, 'academic_term_id' => $subject->academic_term_id,
            'teacher_id' => $teacher->id, 'passing_score' => 75,
            'is_published' => $published, 'published_at' => $published ? now() : null,
        ]);

        $quiz->questions()->create([
            'question' => 'Which one stores a value?',
            'options_json' => ['A' => 'A loop', 'B' => 'A variable'],
            'correct_answer' => 'B',
        ]);

        return $quiz;
    }
}
