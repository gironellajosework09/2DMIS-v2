<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_ATTEMPTS = 5;

    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'username' => 'jordi',
            'password' => 'secret123',
        ], $overrides));
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_by_username(): void
    {
        $user = $this->createUser();

        $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull($user->fresh()->session_token);
        $this->assertSame($user->fresh()->session_token, session('session_token'));
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->createUser();

        $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /**
     * v1 login.php trims the username before the lookup; v2 must accept
     * padded input identically.
     */
    public function test_login_accepts_padded_username(): void
    {
        $user = $this->createUser();

        $this->post(route('login.attempt'), [
            'username' => '  jordi ',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    /**
     * The throttle key must be built from the same normalized username as
     * the authentication attempt, or padded input would get a fresh bucket.
     */
    public function test_padded_username_shares_the_plain_throttle_bucket(): void
    {
        $user = $this->createUser();

        foreach (range(1, self::MAX_ATTEMPTS) as $attempt) {
            $this->post(route('login.attempt'), [
                'username' => 'jordi',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $response = $this->post(route('login.attempt'), [
            'username' => ' jordi ',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('username'),
        );
        $this->assertGuest();
        $this->assertNull($user->fresh()->session_token);
    }

    public function test_repeated_failed_attempts_lock_out_even_valid_credentials(): void
    {
        $this->createUser();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.attempt'), [
                'username' => 'jordi',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $response = $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('username'),
        );
        $this->assertGuest();
    }

    public function test_throttled_account_stays_locked_with_wrong_password(): void
    {
        $user = $this->createUser();

        foreach (range(1, 6) as $attempt) {
            $this->post(route('login.attempt'), [
                'username' => 'jordi',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame($user->fresh()->session_token, null);
    }

    public function test_successful_login_remains_possible_below_the_threshold(): void
    {
        $user = $this->createUser();

        foreach (range(1, 2) as $attempt) {
            $this->post(route('login.attempt'), [
                'username' => 'jordi',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->session_token);
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_clears_session_and_token(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->session_token);
    }

    public function test_second_device_login_invalidates_first_device(): void
    {
        $user = $this->createUser();

        $firstToken = 'token-device-a';
        $user->session_token = $firstToken;
        $user->save();

        // First device session is valid when its token matches the DB.
        $this->withSession(['session_token' => $firstToken])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        // Clear the in-memory guard and session so the next POST is a guest,
        // simulating a completely different browser (device B).
        $this->app['auth']->forgetGuards();
        $this->withSession([]);

        // A second login overwrites the DB token.
        $this->post(route('login.attempt'), [
            'username' => 'jordi',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotSame($firstToken, $user->fresh()->session_token);

        // The first device's stale session now fails the single-device check.
        // $user->fresh() re-reads the DB so the guard sees the new token,
        // matching what a real session-based request would load.
        $this->withSession(['session_token' => $firstToken])
            ->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('login_status', 'expired');
    }
}
