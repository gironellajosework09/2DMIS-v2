<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

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
