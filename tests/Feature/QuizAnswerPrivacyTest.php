<?php

namespace Tests\Feature;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correct answers must not reach a student who could pass them to classmates who
 * have not taken the quiz yet.
 */
class QuizAnswerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private User $student;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $term    = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level   = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand  = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $section = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student($section)->create();

        $this->quiz = Quiz::create([
            'title' => 'Quiz 1', 'subject_id' => $subject->id, 'academic_term_id' => $term->id,
            'teacher_id' => $this->teacher->id, 'passing_score' => 50,
            'is_published' => true, 'published_at' => now(),
        ]);
        $this->quiz->questions()->create([
            'question' => 'Stores a value?',
            'options_json' => ['A' => 'A loop', 'B' => 'A variable'],
            'correct_answer' => 'B',
        ]);
        $this->quiz->sections()->sync([$section->id]);
    }

    public function test_a_new_quiz_hides_its_answers_unless_the_teacher_says_otherwise(): void
    {
        $this->assertFalse($this->quiz->fresh()->reveal_answers, 'answers are hidden by default');
    }

    public function test_the_page_a_student_answers_on_never_contains_the_correct_answer(): void
    {
        $html = $this->actingAs($this->student)
            ->get(route('student.quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Stores a value?')
            ->getContent();

        // Both options are offered, but nothing says which one is right
        $this->assertStringContainsString('A variable', $html);
        $this->assertStringNotContainsString('correct_answer', $html);
        $this->assertStringNotContainsString('Correct answer', $html);
    }

    public function test_after_submitting_the_answers_stay_hidden(): void
    {
        $question = $this->quiz->questions()->first();

        $this->actingAs($this->student)
            ->post(route('student.quizzes.submit', $this->quiz), ['answers' => [$question->id => 'A']])
            ->assertRedirect(route('student.quizzes.show', $this->quiz));

        $this->actingAs($this->student)->get(route('student.quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('not shown while others are still taking')
            ->assertDontSee('Correct answer');      // the marker on the right option
    }

    public function test_a_teacher_can_still_show_them_once_everyone_has_finished(): void
    {
        $question = $this->quiz->questions()->first();

        $this->actingAs($this->student)
            ->post(route('student.quizzes.submit', $this->quiz), ['answers' => [$question->id => 'A']]);

        $this->quiz->update(['reveal_answers' => true]);

        $this->actingAs($this->student)->get(route('student.quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Review your answers')
            ->assertSee('Correct answer');
    }
}
