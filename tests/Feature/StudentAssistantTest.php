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
use App\Support\StudentAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The help assistant: it explains how to use EduVers and nothing else.
 */
class StudentAssistantTest extends TestCase
{
    use RefreshDatabase;

    private StudentAssistant $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['assistant.ai.key' => '']);   // the guide alone, as it ships
        $this->assistant = new StudentAssistant();
    }

    public function test_it_points_a_student_at_the_page_they_are_looking_for(): void
    {
        $cases = [
            'where can i see my grades'        => ['My Grades', route('student.grades')],
            'how do i know my score'           => ['My Grades', route('student.grades')],
            'what subjects do i have'          => ['My Subjects', route('student.subjects.index')],
            'where are the lessons'            => ['Lessons', route('student.subjects.index')],
            'how do i take a quiz'             => ['Quizzes', route('student.quizzes.index')],
            'i forgot my password'             => ['My Profile', route('profile.edit')],
            'what is the bell for'             => ['bell', route('notifications.index')],
        ];

        foreach ($cases as $question => [$expected, $url]) {
            $reply = $this->assistant->answer($question);

            $this->assertStringContainsString($expected, $reply['answer'], "asked: {$question}");
            $this->assertSame($url, $reply['url'], "asked: {$question}");
            $this->assertSame('guide', $reply['source']);
        }
    }

    public function test_it_refuses_to_help_with_the_quiz_itself(): void
    {
        $offLimits = [
            'what is the answer to question 3',
            'which option is correct for number 5',
            'solve 24 divided by 6 for me',
            'write my essay about rizal',
            'what is the capital of japan',
        ];

        foreach ($offLimits as $question) {
            $reply = $this->assistant->answer($question);

            $this->assertStringContainsString('only help with using EduVers', $reply['answer'], "asked: {$question}");
            $this->assertNull($reply['url']);
        }
    }

    public function test_with_no_ai_key_nothing_is_ever_sent_anywhere(): void
    {
        Http::fake();

        $this->assistant->answer('what is the capital of japan');
        $this->assistant->answer('where are my grades');

        Http::assertNothingSent();
    }

    public function test_the_guide_answers_without_calling_the_ai_even_when_a_key_is_set(): void
    {
        config(['assistant.ai.key' => 'test-key']);
        Http::fake();

        $reply = (new StudentAssistant())->answer('where can i see my grades');

        Http::assertNothingSent();                 // a known question costs nothing
        $this->assertSame('guide', $reply['source']);
    }

    public function test_an_unknown_question_goes_to_the_ai_when_a_key_is_set(): void
    {
        config(['assistant.ai.key' => 'test-key']);

        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Try the menu on the left.']]]]],
        ])]);

        $reply = (new StudentAssistant())->answer('is there a dark mode somewhere');

        $this->assertSame('Try the menu on the left.', $reply['answer']);
        $this->assertSame('ai', $reply['source']);

        // The rules travel with every request, so the service cannot be asked for anything else
        Http::assertSent(fn ($request) => str_contains($request->body(), 'only help with using EduVers')
            && str_contains($request->body(), 'refuse'));
    }

    public function test_when_the_ai_is_unreachable_the_student_still_gets_an_answer(): void
    {
        config(['assistant.ai.key' => 'test-key']);
        Http::fake(['*generativelanguage*' => Http::response('', 503)]);

        $reply = (new StudentAssistant())->answer('is there a dark mode somewhere');

        $this->assertStringContainsString('only help with using EduVers', $reply['answer']);
    }

    public function test_only_a_signed_in_student_can_use_it(): void
    {
        $this->postJson(route('student.assistant'), ['question' => 'where are my grades'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->teacher()->create())
            ->postJson(route('student.assistant'), ['question' => 'where are my grades'])
            ->assertForbidden();

        $this->actingAs(User::factory()->student()->create())
            ->postJson(route('student.assistant'), ['question' => 'where are my grades'])
            ->assertOk()
            ->assertJsonPath('source', 'guide')
            ->assertJsonStructure(['answer', 'url', 'label', 'source']);
    }

    public function test_the_help_button_is_gone_while_a_quiz_is_being_answered(): void
    {
        $term    = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level   = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand  = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $section = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        $quiz = Quiz::create([
            'title' => 'Quiz 1', 'subject_id' => $subject->id, 'academic_term_id' => $term->id,
            'teacher_id' => User::factory()->teacher()->create()->id,
            'passing_score' => 50, 'is_published' => true, 'published_at' => now(),
        ]);
        $quiz->questions()->create(['question' => 'Stores a value?', 'options_json' => ['A' => 'Loop', 'B' => 'Variable'], 'correct_answer' => 'B']);
        $quiz->sections()->sync([$section->id]);

        $student = User::factory()->student($section)->create();

        // On an ordinary page the student can ask for help
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()->assertSee('data-assistant', false)->assertSee('Need help?');

        // While answering, it is not on the page at all
        $this->actingAs($student)->get(route('student.quizzes.show', $quiz))
            ->assertOk()
            ->assertSee($quiz->title)
            ->assertDontSee('data-assistant', false)
            ->assertDontSee('Need help?');
    }

    public function test_an_empty_or_oversized_question_is_refused(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->postJson(route('student.assistant'), ['question' => ''])
            ->assertStatus(422);

        $this->actingAs($student)->postJson(route('student.assistant'), ['question' => str_repeat('a', 501)])
            ->assertStatus(422);
    }
}
