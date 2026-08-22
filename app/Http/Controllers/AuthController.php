<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_DECAY_SECONDS = 60;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        // v1 login.php trims the username before the lookup; the throttle
        // key must use the same normalized value or padded input would get
        // its own attempt bucket.
        $username = trim((string) $request->input('username'));

        $throttleKey = strtolower($username).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $credentials = [
            'username' => $username,
            'password' => (string) $request->input('password'),
        ];

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);

            $user = Auth::user();

            $sessionToken = bin2hex(random_bytes(32));
            $user->session_token = $sessionToken;
            $user->save();

            $request->session()->regenerate();
            $request->session()->put('session_token', $sessionToken);

            app(AuditService::class)->log($user->id, 'LOGIN', 'tbl_users', $user->id);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, self::LOCKOUT_DECAY_SECONDS);

        return back()
            ->withErrors(['username' => 'Invalid username or password.'])
            ->onlyInput('username');
    }

    public function logout(): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            $user->session_token = null;
            $user->save();

            app(AuditService::class)->log($user->id, 'LOGOUT', 'tbl_users', $user->id);
        }

        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }
}
