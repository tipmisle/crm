<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('registracija', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('registracija', [RegisteredUserController::class, 'store']);

    Route::get('prijava', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('prijava', [AuthenticatedSessionController::class, 'store']);

    Route::get('pozabljeno-geslo', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('pozabljeno-geslo', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('ponastavitev-gesla/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('ponastavitev-gesla', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Named 'password.confirm.app', not the more natural 'password.confirm'
    // — Laravel Fortify (see App\Providers\FortifyServiceProvider) always
    // registers its own '/user/confirm-password' route under the name
    // 'password.confirm' too (unconditionally, independent of which Fortify
    // features are enabled), and Laravel's route name resolution is
    // first-registered-wins. Fortify's provider boots before this file
    // loads, so it permanently wins the 'password.confirm' name — pre-
    // existing and unrelated to this URL localization. See the
    // 'password.confirm' middleware alias override in bootstrap/app.php,
    // which is what actually makes RequirePassword redirect here.
    Route::get('potrdi-geslo', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm.app');

    Route::post('potrdi-geslo', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// Legacy GET-only redirects for bookmarked/old auth URLs. GET-only so a
// stale POST (e.g. a cached login/register form action) 404s instead of
// being silently redirected with its method potentially downgraded.
Route::get('register', fn () => redirect('/registracija', 301));
Route::get('login', fn () => redirect('/prijava', 301));
Route::get('forgot-password', fn () => redirect('/pozabljeno-geslo', 301));
Route::get('reset-password/{token}', fn (string $token) => redirect('/ponastavitev-gesla/'.$token, 301));
Route::get('confirm-password', fn () => redirect('/potrdi-geslo', 301));
