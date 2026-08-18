<?php

namespace App\Providers;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Billing belongs to the Workspace (the paying business), never to
        // an individual User — see docs/billing.md. Never call
        // Cashier::calculateTaxes() (default already off) — Stripe Tax is
        // deliberately not enabled this milestone, see NEEDS OWNER INPUT.
        // Must run during register(), not boot() — CashierServiceProvider
        // registers its own routes/webhook controller during ITS boot(),
        // so ignoreRoutes() has to be set before any provider's boot phase
        // runs, or it registers them anyway.
        Cashier::useCustomerModel(Workspace::class);

        // We register our own webhook route (StripeWebhookController) with
        // custom event handlers instead of Cashier's default controller —
        // see routes/web.php.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Carbon::setLocale(config('app.locale'));

        // When APP_URL is https (e.g. tunneled through ngrok for local Meta
        // OAuth/webhook testing), force generated URLs (assets, route(),
        // OAuth redirect) to https too — the tunnel terminates TLS before
        // Laravel ever sees the request, so it would otherwise infer http.
        if (Str::startsWith(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Sensitive platform-admin mutations (starting a support session,
        // deactivating accounts, deleting demo workspaces, clearing
        // integration errors) already require a recently-confirmed
        // password (routes/admin.php); this adds a per-admin ceiling so a
        // compromised or careless admin session can't hammer them.
        RateLimiter::for('admin-mutations', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?? $request->ip());
        });
    }
}
