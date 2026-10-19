<?php

namespace Tests\Feature;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TrackStrand;
use App\Support\SchoolProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three panels on the sign-in page: built from the school's own year levels, strands
 * and sections, so changing those in the admin changes what a visitor sees.
 */
class SchoolProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_panels_describe_the_levels_strands_and_courses_that_exist(): void
    {
        $g11 = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        GradeLevel::create(['name' => 'Grade 12', 'level_type' => LevelType::Shs]);     // no sections yet
        $yr1 = GradeLevel::create(['name' => '1st Year', 'level_type' => LevelType::College]);

        $ict  = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        $abm  = TrackStrand::create(['name' => 'ABM', 'category' => TrackCategory::Academic]);
        $hrs  = TrackStrand::create(['name' => 'HRS', 'category' => TrackCategory::College]);

        Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id]);
        Section::create(['name' => 'ABM 11-A', 'capacity' => 40, 'grade_level_id' => $g11->id, 'track_strand_id' => $abm->id]);
        Section::create(['name' => 'HRS 1-A', 'capacity' => 35, 'grade_level_id' => $yr1->id, 'track_strand_id' => $hrs->id]);

        [$shs, $college, $tracks] = SchoolProfile::tiles();

        $this->assertSame('Grade 11 & Grade 12', $shs['value']);
        $this->assertSame(['name' => 'Grade 11', 'detail' => 'ABM & ICT'], $shs['rows'][0]);
        $this->assertSame(['name' => 'Grade 12', 'detail' => 'No sections yet'], $shs['rows'][1]);

        $this->assertSame('HRS', $college['value'], 'College is known by its courses');
        $this->assertSame('Academic, College & TechPro', $tracks['value']);
        $this->assertContains(['name' => 'TechPro', 'detail' => 'ICT'], $tracks['rows']);
    }

    public function test_a_visitor_sees_them_on_the_sign_in_page_and_can_open_each_one(): void
    {
        $g11 = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $ict = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);
        Section::create(['name' => 'ICT 11-A', 'capacity' => 40, 'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Grade 11')                       // the tile's own value
            ->assertSee('data-dialog-open="offering-senior-high"', false)
            ->assertSee('<dialog id="offering-tracks"', false)
            ->assertSee('Tracks, strands &amp; courses', false)
            ->assertSee('TechPro');                       // inside the popup
    }

    public function test_a_school_with_nothing_set_up_yet_says_so_instead_of_breaking(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Coming soon');

        foreach (SchoolProfile::tiles() as $tile) {
            $this->assertSame('Coming soon', $tile['value']);
            $this->assertSame([], $tile['rows']);
        }
    }
}
