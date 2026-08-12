<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\GeographyController;
use App\Http\Controllers\GranteeSearchController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\PayoutAttendanceController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScholarController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnpaidVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('session/status', [SessionController::class, 'status'])->name('session.status');

Route::get('student/update-photo', [StudentController::class, 'updatePhoto'])->name('student.update-photo');
Route::get('student/verify/{client}', [StudentController::class, 'verify'])->name('student.verify');
Route::post('student/verify/{client}', [StudentController::class, 'verify'])->name('student.verify.post');
Route::get('student/photo-upload', [StudentController::class, 'photoUpload'])->name('student.photo-upload');
Route::post('student/photo-upload', [StudentController::class, 'storePhoto'])->name('student.photo-upload.store');

// P5 public self-service unpaid verification (v1 disabled_unpaid.php +
// unpaid_save.php + search_unpaid_grantee.php have no session check — the
// form is a public page grantees use at the payout venue).
Route::get('unpaid-verification', [UnpaidVerificationController::class, 'selfService'])
    ->name('unpaid-verification.self-service');
Route::post('unpaid-verification/submit', [UnpaidVerificationController::class, 'store'])
    ->name('unpaid-verification.submit');

Route::get('grantee-search/{kind}', [GranteeSearchController::class, 'search'])->name('grantee-search');
Route::post('grantee-search/{kind}', [GranteeSearchController::class, 'verify'])->name('grantee-search.verify');

Route::middleware(['auth', 'single-device'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('session/online', [SessionController::class, 'online'])
        ->name('session.online')
        ->middleware('page:currently_logged_users.php');

    Route::post('session/force-logout', [SessionController::class, 'forceLogout'])
        ->name('session.force-logout')
        ->middleware('page:force_logout.php');

    Route::get('geography/barangays', [GeographyController::class, 'barangays'])
        ->name('geography.barangays');

    Route::middleware('page:clients.php')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/verify-mobile', [ClientController::class, 'verifyMobile'])->name('clients.verify-mobile');
        Route::get('clients/duplicates', [DuplicateController::class, 'index'])->name('duplicates.index');
        Route::post('clients/duplicates/data', [DuplicateController::class, 'data'])->name('duplicates.data');
        Route::post('clients/duplicates/delete', [DuplicateController::class, 'destroy'])->name('duplicates.destroy');
        Route::post('clients/photo', [PhotoController::class, 'store'])->name('clients.photo.store');
        Route::post('clients/data', [ClientController::class, 'data'])->name('clients.data');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::post('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });

    Route::middleware('page:household.php')->group(function () {
        Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
        Route::get('households/create', [HouseholdController::class, 'create'])->name('households.create');
        Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
        Route::post('households/data', [HouseholdController::class, 'data'])->name('households.data');
        Route::get('households/search', [HouseholdController::class, 'search'])->name('households.search');
        Route::get('households/clients/search', [HouseholdController::class, 'searchClientsForHousehold'])
            ->name('households.clients.search');
        Route::get('households/clients/{client}', [HouseholdController::class, 'clientOptions'])
            ->name('households.clients.options');
        Route::get('households/{household}', [HouseholdController::class, 'show'])->name('households.show');
        Route::post('households/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    });

    Route::middleware('page:clients.php')->group(function () {
        Route::get('family-members/search', [FamilyMemberController::class, 'search'])
            ->name('family-members.search');
        Route::get('family-members/{client}', [FamilyMemberController::class, 'create'])
            ->name('family-members.create');
        Route::post('family-members/{client}', [FamilyMemberController::class, 'store'])
            ->name('family-members.store');
    });

    Route::middleware('page:all_transactions.php')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/create/{client}', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
        Route::post('transactions/data', [TransactionController::class, 'data'])->name('transactions.data');
        Route::post('transactions/inline-update', [TransactionController::class, 'inlineUpdate'])->name('transactions.inline-update');
        Route::get('transactions/clients-search', [TransactionController::class, 'searchClients'])->name('transactions.clients-search');
        Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    });

    Route::middleware('page:scholars.php')->group(function () {
        Route::get('scholars', [ScholarController::class, 'index'])->name('scholars.index');
        Route::post('scholars/data', [ScholarController::class, 'data'])->name('scholars.data');
        Route::get('scholars/create', [ScholarController::class, 'create'])->name('scholars.create');
        Route::post('scholars', [ScholarController::class, 'store'])->name('scholars.store');
        Route::get('scholars/{scholar}/edit', [ScholarController::class, 'edit'])->name('scholars.edit');
        Route::put('scholars/{scholar}', [ScholarController::class, 'update'])->name('scholars.update');
        Route::post('scholars/update-client-id', [ScholarController::class, 'updateClientId'])->name('scholars.update-client-id');
    });

    // P4 scanner engine — one GET page + lookup + save route per scanner key,
    // each gated with the matching v1 page name (page:scanner_*.php). Each
    // registration defaults the {key} route parameter to its literal value so
    // route names need no argument and the controller still receives the key.
    foreach (config('scanner.scanners') as $scannerKey => $scannerConfig) {
        Route::middleware('page:'.$scannerConfig['page'])->group(function () use ($scannerKey) {
            Route::get('scanners/'.$scannerKey, [ScannerController::class, 'show'])
                ->defaults('key', $scannerKey)
                ->name('scanners.'.$scannerKey);
            Route::post('scanners/'.$scannerKey.'/lookup', [ScannerController::class, 'lookup'])
                ->defaults('key', $scannerKey)
                ->name('scanners.'.$scannerKey.'.lookup');
            Route::post('scanners/'.$scannerKey.'/save', [ScannerController::class, 'save'])
                ->defaults('key', $scannerKey)
                ->name('scanners.'.$scannerKey.'.save');
        });
    }

    // P5 payout attendance — one shared view + feed per variant, each gated
    // with its own v1 page key (page:scanned_payouts*.php). Same pattern as
    // the scanner loop: config drives the variant, defaults() feeds it in.
    foreach (config('payout.attendance') as $variant => $attendanceConfig) {
        Route::middleware('page:'.$attendanceConfig['page'])->group(function () use ($variant) {
            Route::get('payout-attendance/'.$variant, [PayoutAttendanceController::class, 'index'])
                ->defaults('variant', $variant)
                ->name('payout-attendance.'.$variant.'.index');
            Route::post('payout-attendance/'.$variant.'/data', [PayoutAttendanceController::class, 'data'])
                ->defaults('variant', $variant)
                ->name('payout-attendance.'.$variant.'.data');
        });
    }

    Route::middleware('page:unpaid_verifications.php')->group(function () {
        Route::get('unpaid-verifications', [UnpaidVerificationController::class, 'index'])
            ->name('unpaid-verifications.index');
        Route::post('unpaid-verifications/data', [UnpaidVerificationController::class, 'data'])
            ->name('unpaid-verifications.data');
        Route::get('unpaid-verifications/export', [UnpaidVerificationController::class, 'export'])
            ->name('unpaid-verifications.export');
    });
});
