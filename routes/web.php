<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('session/status', [SessionController::class, 'status'])->name('session.status');

Route::middleware(['auth', 'single-device'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('session/online', [SessionController::class, 'online'])
        ->name('session.online')
        ->middleware('page:currently_logged_users.php');

    Route::post('session/force-logout', [SessionController::class, 'forceLogout'])
        ->name('session.force-logout')
        ->middleware('page:force_logout.php');
});
