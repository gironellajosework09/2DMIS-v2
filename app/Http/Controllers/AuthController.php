<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('username', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            $sessionToken = bin2hex(random_bytes(32));
            $user->session_token = $sessionToken;
            $user->save();

            $request->session()->regenerate();
            $request->session()->put('session_token', $sessionToken);

            app(AuditService::class)->log($user->id, 'LOGIN', 'tbl_users', $user->id);

            return redirect()->intended(route('dashboard'));
        }

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
