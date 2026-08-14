<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
