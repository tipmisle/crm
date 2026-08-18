<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

/**
 * Deliberately minimal: only the 'twoFactorAuthentication' feature is
 * enabled in config/fortify.php — registration/password-reset/profile-
 * update/password-update all stay with this app's own existing
 * controllers (routes/auth.php, routes/web.php), not Fortify's. This
 * provider only wires the 2FA challenge view to an Inertia page and the
 * rate limiter Fortify's own two-factor-challenge route depends on
 * (`throttle:two-factor` — see route:list). Enable/confirm/disable/
 * recovery-code endpoints are Fortify's own controllers/actions, called
 * directly from Profile's security settings page via Inertia form posts;
 * no controller of ours wraps them.
 *
 * See docs/pre-launch-security.md "MFA" section.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Demo visitors are blocked from the mutating 2FA endpoints by
        // App\Http\Middleware\DenyDemoTwoFactorMutation, registered
        // globally in bootstrap/app.php (see that class's docblock for why
        // it isn't attached to Fortify's route objects directly here).
    }
}
