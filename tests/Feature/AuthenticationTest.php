<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Transform, Learn, Lead');
    }

    public function test_users_can_log_in_and_are_sent_to_their_dashboard(): void
    {
        $user = User::factory()->teacher()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('teacher.dashboard'));
    }

    public function test_login_returns_to_the_page_the_user_wanted_if_their_role_allows_it(): void
    {
        $teacher = User::factory()->teacher()->create(['password' => 'secret-pass']);

        $this->get(route('teacher.lessons.index'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('teacher.lessons.index'));
    }

    public function test_login_ignores_a_leftover_link_from_another_role(): void
    {
        $teacher = User::factory()->teacher()->create(['password' => 'secret-pass']);

        // e.g. a developer's session expired on the developer dashboard
        $this->get(route('developer.dashboard'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('teacher.dashboard'));
    }

    public function test_users_cannot_log_in_with_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_signed_in_users_are_sent_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->developer()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_users_can_change_their_password_with_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'password',
                'password'              => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'not-my-password',
                'password'              => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
