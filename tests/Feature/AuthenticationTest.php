<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

        // e.g. an admin's session expired on the admin dashboard
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

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
        $this->actingAs(User::factory()->admin()->create())
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

    public function test_users_are_signed_out_after_30_minutes_without_activity(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        // The bell's background check is not activity
        $this->travel(20)->minutes();
        $this->get(route('notifications.count'))->assertOk();

        $this->travel(11)->minutes();
        $this->get(route('student.grades'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You were signed out after 30 minutes of inactivity.');
        $this->assertGuest();
    }

    public function test_activity_and_the_page_ping_keep_users_signed_in(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        $this->travel(25)->minutes();
        $this->get(route('session.ping'))->assertNoContent();

        $this->travel(25)->minutes();
        $this->get(route('student.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($student);
    }

    public function test_forgot_password_emails_a_reset_link_without_revealing_who_has_an_account(): void
    {
        Notification::fake();
        $user = User::factory()->teacher()->create();

        $this->get(route('login'))->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk();

        $known   = $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_the_emailed_link_sets_a_new_password_once(): void
    {
        Notification::fake();
        $user = User::factory()->teacher()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        // The email uses the EduVers theme and links to the reset page
        $mail = (new ResetPassword($token))->toMail($user);
        $this->assertSame('eduvers', $mail->theme);
        $this->assertSame(route('password.reset', ['token' => $token, 'email' => $user->email]), $mail->actionUrl);

        $reset = ['token' => $token, 'email' => $user->email, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'];
        $this->post(route('password.update'), $reset)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));

        // The link can't be used a second time
        $this->post(route('password.update'), ['password' => 'Other1234', 'password_confirmation' => 'Other1234'] + $reset)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }
}
