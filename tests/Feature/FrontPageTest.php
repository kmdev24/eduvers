<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The front page of the site: what the school offers, with signing in on the same page.
 */
class FrontPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_site_opens_on_the_front_page_rather_than_a_bare_login_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Where excellence is')      // the hero
            ->assertSee('Transform, Learn, Lead')
            ->assertSee('Sign in to EduVers');      // ... with the form on the same page
    }

    public function test_the_old_login_address_still_works(): void
    {
        $this->get('/login')->assertRedirect('/');

        // Everything that sends guests to sign in points at the front page
        $this->assertSame(url('/'), route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_signed_in_visitor_goes_to_their_own_dashboard_instead(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
