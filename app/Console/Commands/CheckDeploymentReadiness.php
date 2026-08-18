<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Pre-deploy / post-deploy operational gate: verifies the runtime plumbing
 * (DB reachability, queue/session/broadcast config, required third-party
 * credentials) a real production request depends on. Complements
 * `legal:check` (public legal copy) — this command is about infrastructure
 * config, not content. Meant to be run manually or from a deploy pipeline,
 * never automatically at request boot (must never risk breaking local dev
 * or the test suite). See docs/production-launch.md.
 *
 * Never prints a secret's value — only whether it's present/absent.
 */
class CheckDeploymentReadiness extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Report missing/unsafe production configuration before or after a deploy.';

    public function handle(): int
    {
        // config('app.env') rather than app()->environment() — the latter is
        // resolved once at boot from the real APP_ENV and isn't swappable by
        // overriding config() in a test.
        $isProduction = config('app.env') === 'production';
        $failures = 0;

        $this->line('Environment: '.config('app.env').($isProduction ? '' : ' (production-only checks skipped)'));

        $failures += $this->checkDatabase();
        $failures += $this->checkAppBasics($isProduction);
        $failures += $this->checkSession($isProduction);
        $failures += $this->checkQueue($isProduction);
        $failures += $this->checkStripe($isProduction);
        $failures += $this->checkMeta($isProduction);
        $failures += $this->checkReverb($isProduction);
        $failures += $this->checkWebPush();
        $failures += $this->checkLegal($isProduction);
        $failures += $this->checkAdminMfa($isProduction);

        if ($failures > 0) {
            $this->error("{$failures} deployment check(s) failed.");

            return self::FAILURE;
        }

        $this->info('deploy:check passed.');

        return self::SUCCESS;
    }

    private function checkDatabase(): int
    {
        try {
            DB::connection()->getPdo();
            $this->info('DB: connection OK ('.DB::connection()->getDriverName().').');

            return 0;
        } catch (\Throwable $e) {
            // Never surface the raw exception message here — it can contain
            // the DB host/username in some driver error strings.
            $this->error('DB: could not connect. Check DB_* environment variables.');

            return 1;
        }
    }

    private function checkAppBasics(bool $isProduction): int
    {
        $failures = 0;

        if (blank(config('app.key'))) {
            $this->error('APP_KEY is not set — sessions, encrypted casts, and CSRF tokens are all broken without it.');
            $failures++;
        }

        if (! $isProduction) {
            return $failures;
        }

        if (config('app.debug') === true) {
            $this->error('APP_DEBUG=true in production — exposes stack traces/config to visitors on error. Set APP_DEBUG=false.');
            $failures++;
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $this->error('APP_URL does not start with https:// — generated URLs and forced HTTPS (AppServiceProvider) depend on this.');
            $failures++;
        }

        return $failures;
    }

    private function checkSession(bool $isProduction): int
    {
        if (! $isProduction) {
            return 0;
        }

        if (config('session.secure') !== true) {
            $this->error('SESSION_SECURE_COOKIE is not true in production — session cookies can be sent over plain HTTP.');

            return 1;
        }

        $this->info('Session: secure cookie enabled.');

        return 0;
    }

    private function checkQueue(bool $isProduction): int
    {
        $connection = config('queue.default');
        $this->line("Queue connection: {$connection}");

        if ($isProduction && $connection === 'sync') {
            $this->error('QUEUE_CONNECTION=sync in production — queued jobs (Meta webhook ingestion, profile lookups) run inline on the request instead of a worker, with no retry/backoff. A queue worker must also be running continuously for any non-sync connection — see docs/production-launch.md.');

            return 1;
        }

        return 0;
    }

    private function checkStripe(bool $isProduction): int
    {
        if (! $isProduction) {
            return 0;
        }

        $failures = 0;
        $required = [
            'cashier.key' => 'STRIPE_KEY',
            'cashier.secret' => 'STRIPE_SECRET',
            'cashier.webhook.secret' => 'STRIPE_WEBHOOK_SECRET',
            'billing.monthly_price_id' => 'STRIPE_BELEZKA_MONTHLY_PRICE_ID',
        ];

        foreach ($required as $configKey => $envName) {
            if (blank(config($configKey))) {
                $this->error("Missing required Stripe config: {$envName}. Without STRIPE_WEBHOOK_SECRET specifically, webhook signature verification is never attached (Cashier only registers it when this is set) — checkout will appear to work but subscriptions will never activate.");
                $failures++;
            }
        }

        return $failures;
    }

    /**
     * Advisory only, never blocking: a production deployment with no Meta
     * channel connected yet is legitimate, so this warns but never fails
     * deploy:check.
     */
    private function checkMeta(bool $isProduction): int
    {
        if (! $isProduction) {
            return 0;
        }

        $required = [
            'meta.app_id' => 'META_APP_ID',
            'meta.app_secret' => 'META_APP_SECRET',
            'meta.webhook_verify_token' => 'META_WEBHOOK_VERIFY_TOKEN',
        ];

        foreach ($required as $configKey => $envName) {
            if (blank(config($configKey))) {
                $this->warn("Meta config not set: {$envName}. Only relevant once a workspace connects an Instagram/Messenger channel.");
            }
        }

        return 0;
    }

    private function checkReverb(bool $isProduction): int
    {
        if (! $isProduction || config('broadcasting.default') !== 'reverb') {
            return 0;
        }

        $failures = 0;
        $required = [
            'broadcasting.connections.reverb.key' => 'REVERB_APP_KEY',
            'broadcasting.connections.reverb.secret' => 'REVERB_APP_SECRET',
            'broadcasting.connections.reverb.app_id' => 'REVERB_APP_ID',
        ];

        foreach ($required as $configKey => $envName) {
            if (blank(config($configKey))) {
                $this->error("Missing required Reverb config: {$envName} — realtime Inbox updates will fail (the 30s poll fallback still works, but every message will feel delayed).");
                $failures++;
            }
        }

        if (config('broadcasting.connections.reverb.options.useTLS') !== true) {
            $this->warn('Reverb REVERB_SCHEME is not https — production should terminate TLS (wss://) in front of Reverb, not serve plain ws://.');
        }

        return $failures;
    }

    private function checkWebPush(): int
    {
        if (blank(config('webpush.vapid.public_key')) || blank(config('webpush.vapid.private_key'))) {
            $this->warn('VAPID keys not set — follow-up due reminders (App\Notifications\FollowUpDue) will silently fail to deliver as push notifications. Generate with `php artisan webpush:vapid`.');
        }

        return 0;
    }

    /**
     * A production operator running only `deploy:check` must not miss
     * missing/incomplete legal config — public legal pages and the
     * registration flow depend on it just as much as infrastructure does.
     * `legal:check` is only production-blocking here; it stays a no-op in
     * non-production so local dev/tests aren't forced to fill in owner
     * config that isn't needed yet.
     */
    private function checkLegal(bool $isProduction): int
    {
        if (! $isProduction) {
            return 0;
        }

        $exitCode = Artisan::call('legal:check');
        $this->output->write(Artisan::output());

        if ($exitCode !== 0) {
            $this->error('legal:check failed — see output above. Public legal pages and/or the launch price cannot ship without this resolved.');

            return 1;
        }

        return 0;
    }

    /**
     * Advisory only, never blocking: the /admin panel already redirects any
     * platform admin without confirmed 2FA away from every admin page
     * (EnsurePlatformAdmin), so this can't be bypassed by skipping
     * deploy:check — it's just an early, explicit heads-up for whoever runs
     * this before/after a deploy so an admin isn't surprised at the door.
     */
    private function checkAdminMfa(bool $isProduction): int
    {
        if (! $isProduction) {
            return 0;
        }

        $unprotected = User::query()
            ->where('is_platform_admin', true)
            ->where('is_active', true)
            ->whereNull('two_factor_confirmed_at')
            ->pluck('email');

        if ($unprotected->isNotEmpty()) {
            $this->warn('Platform admin(s) without confirmed 2FA: '.$unprotected->implode(', ').'. They will be redirected to set it up on their next /admin visit, but should enable it proactively.');
        }

        return 0;
    }
}
