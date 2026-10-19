<?php

namespace Tests\Feature;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Year levels (Grade 11-12, 1st-4th Year). Without these an admin cannot build the
 * sections students are enrolled into, so the whole structure starts here.
 */
class GradeLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_an_admin_adds_the_year_levels_the_school_runs(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.levels.store'), ['name' => '3rd Year', 'level_type' => 'college'])
            ->assertSessionHas('status');

        $level = GradeLevel::sole();
        $this->assertSame('3rd Year', $level->name);
        $this->assertSame(LevelType::College, $level->level_type);

        $this->actingAs($this->admin)->get(route('admin.levels.index'))
            ->assertOk()->assertSee('3rd Year')->assertSee('no section yet');
    }

    public function test_two_levels_cannot_share_a_name(): void
    {
        GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);

        $this->actingAs($this->admin)
            ->post(route('admin.levels.store'), ['name' => 'Grade 12', 'level_type' => 'college'])
            ->assertSessionHasErrors('name', null, 'level');

        $this->assertSame(1, GradeLevel::count());
    }

    public function test_a_level_in_use_cannot_move_between_senior_high_and_college_or_be_deleted(): void
    {
        $level  = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.levels.update', $level), ['name' => 'Grade 11', 'level_type' => 'college'])
            ->assertSessionHasErrors('level_type');

        $this->assertSame(LevelType::Shs, $level->fresh()->level_type);

        $this->actingAs($this->admin)
            ->delete(route('admin.levels.destroy', $level))
            ->assertSessionHas('error');

        $this->assertNotNull($level->fresh());
    }

    public function test_an_unused_level_can_be_renamed_and_deleted(): void
    {
        $level = GradeLevel::create(['name' => '3rd Yr', 'level_type' => LevelType::College]);

        $this->actingAs($this->admin)
            ->put(route('admin.levels.update', $level), ['name' => '3rd Year', 'level_type' => 'college'])
            ->assertRedirect(route('admin.levels.index'));

        $this->assertSame('3rd Year', $level->fresh()->name);

        $this->actingAs($this->admin)->delete(route('admin.levels.destroy', $level))->assertSessionHas('status');
        $this->assertNull($level->fresh());
    }

    public function test_the_add_user_form_offers_every_year_level_even_one_with_no_section(): void
    {
        $term    = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $g11     = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $fourth  = GradeLevel::create(['name' => '4th Year', 'level_type' => LevelType::College]); // no section at all
        $ict     = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $section = Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id]);

        Subject::create(['code' => 'ICT-PROG1', 'name' => 'Computer Programming 1', 'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id, 'academic_term_id' => $term->id]);

        $this->actingAs($this->admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Grade 11 · Senior High School')
            ->assertSee('4th Year · College')        // listed even though it has no section
            ->assertSee('ICT · TechPro')
            ->assertSee('ICT 11-A · ICT (0/40)');

        // Editing a student pre-selects the level and strand of the section they are in
        $student = User::factory()->student($section)->create();

        $html = $this->actingAs($this->admin)->get(route('admin.users.edit', $student))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="'.$g11->id.'"[^>]*data-level-type="shs"[^>]*selected/s', $html, 'year level pre-selected');
        $this->assertMatchesRegularExpression('/value="'.$ict->id.'"[^>]*data-category="techpro"[^>]*selected/s', $html, 'strand pre-selected');
        $this->assertMatchesRegularExpression('/value="'.$section->id.'"[^>]*data-grade-level-id="'.$g11->id.'"/s', $html, 'section carries its level for the cascade');
    }
}
