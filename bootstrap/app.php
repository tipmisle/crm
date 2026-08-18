<?php

use App\Http\Middleware\DenyDemoTwoFactorMutation;
use App\Http\Middleware\EnsureAppointmentsEnabled;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureOrdersEnabled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceHasActiveSubscription;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            Route::middleware('web')
                ->group(__DIR__.'/../routes/admin.php');
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Required behind any reverse proxy/load balancer (nginx, Cloudflare,
        // an ALB, etc.) that terminates TLS in front of the app — without
        // this, Laravel ignores X-Forwarded-* and generates http:// URLs /
        // misreads the client IP even though the edge is HTTPS. Unset
        // TRUSTED_PROXIES (the default) trusts nothing, which is correct for
        // local development and any deployment with no proxy in front.
        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            DenyDemoTwoFactorMutation::class,
        ]);

        $middleware->alias([
            'appointments.enabled' => EnsureAppointmentsEnabled::class,
            'orders.enabled' => EnsureOrdersEnabled::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'subscription.active' => EnsureWorkspaceHasActiveSubscription::class,
            'onboarding.gate' => EnsureOnboardingComplete::class,
            // Overrides Laravel's default 'password.confirm' alias, which
            // redirects to route('password.confirm') — a name Laravel
            // Fortify also registers (see routes/auth.php), and always wins
            // over this app's own route there. Pointed at this app's own
            // 'password.confirm.app' route instead, so every route using
            // this middleware (this app's sensitive-action gates, and
            // Fortify's own 2FA enable/disable/recovery-code routes) lands
            // on a working confirm-password page instead of Fortify's
            // unconfigured one.
            'password.confirm' => RequirePassword::using('password.confirm.app'),
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/meta',
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
