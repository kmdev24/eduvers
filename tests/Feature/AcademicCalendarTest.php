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
 * The school calendar decides which term is running, and students see that term's
 * subjects — without anyone remembering to tick a box when a semester turns over.
 */
class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_term_whose_dates_cover_today_is_the_current_one(): void
    {
        $past = $this->term(1, now()->subMonths(6), now()->subMonths(4));
        $now  = $this->term(2, now()->subWeek(), now()->addMonths(3));
        $next = $this->term(3, now()->addMonths(4), now()->addMonths(7));

        $this->assertTrue(AcademicTerm::getCurrent()->is($now));
        $this->assertFalse($past->isRunningToday());
        $this->assertTrue($now->isRunningToday());
        $this->assertFalse($next->isRunningToday());
    }

    public function test_the_calendar_moves_the_school_on_by_itself(): void
    {
        $first  = $this->term(1, now()->subMonth(), now()->addDays(3));
        $second = $this->term(2, now()->addDays(4), now()->addMonths(4));

        $this->assertTrue(AcademicTerm::getCurrent()->is($first));

        // Nobody touches anything; the next term simply starts
        $this->travel(5)->days();

        $this->assertTrue(AcademicTerm::getCurrent()->is($second));
    }

    public function test_a_term_with_no_dates_still_follows_the_hand_set_flag(): void
    {
        $this->term(1, null, null);
        $chosen = $this->term(2, null, null);
        $chosen->makeCurrent();

        $this->assertTrue(AcademicTerm::getCurrent()->is($chosen));
    }

    public function test_a_dated_term_beats_the_flag_so_the_calendar_wins(): void
    {
        $running = $this->term(1, now()->subDay(), now()->addDay());
        $flagged = $this->term(2, null, null);
        $flagged->makeCurrent();

        $this->assertTrue(AcademicTerm::getCurrent()->is($running));
    }

    public function test_students_see_this_terms_subjects_and_keep_their_past_grades(): void
    {
        $firstTerm  = $this->term(1, now()->subMonths(6), now()->subMonths(4));
        $secondTerm = $this->term(2, now()->subWeek(), now()->addMonths(3));

        $level   = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand  = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $section = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $old = Subject::create(['code' => 'ICT-OLD', 'name' => 'Last Term Subject', 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $firstTerm->id]);
        $new = Subject::create(['code' => 'ICT-NEW', 'name' => 'This Term Subject', 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $secondTerm->id]);

        $student = User::factory()->student($section)->create();
        $teacher = User::factory()->teacher()->create();

        // A quiz the student sat last term
        $quiz = Quiz::create([
            'title' => 'Last Term Quiz', 'subject_id' => $old->id, 'academic_term_id' => $firstTerm->id,
            'teacher_id' => $teacher->id, 'passing_score' => 50, 'is_published' => true, 'published_at' => now()->subMonths(5),
        ]);
        $quiz->questions()->create(['question' => 'One?', 'options_json' => ['A' => 'no', 'B' => 'yes'], 'correct_answer' => 'B']);
        $quiz->sections()->sync([$section->id]);
        $quiz->submitFor($student, [$quiz->questions()->first()->id => 'B']);

        $this->actingAs($student)->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee('This Term Subject')
            ->assertDontSee('Last Term Subject');

        $this->actingAs($student)->get(route('student.quizzes.index'))
            ->assertOk()->assertDontSee('Last Term Quiz');

        // The result itself is history and must not disappear with the term
        $this->actingAs($student)->get(route('student.grades'))
            ->assertOk()->assertSee('Last Term Quiz');
    }

    private function term(int $number, ?\DateTimeInterface $from, ?\DateTimeInterface $to): AcademicTerm
    {
        return AcademicTerm::create([
            'name'          => $number.'st Term',
            'term_number'   => $number,
            'academic_year' => '2026-2027',
            'starts_on'     => $from,
            'ends_on'       => $to,
        ]);
    }
}
