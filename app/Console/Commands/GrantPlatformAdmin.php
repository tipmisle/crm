<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deliberately CLI-only. There is no web UI to grant/revoke platform-admin
 * status — that boundary is the most sensitive one in the app, and console
 * access already implies trust the web layer shouldn't have to re-derive.
 */
class GrantPlatformAdmin extends Command
{
    protected $signature = 'admin:grant {email} {--revoke : Revoke platform-admin status instead of granting it}';

    protected $description = 'Grant or revoke platform-admin (Beležka support/ops) status for a user.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        $user->forceFill(['is_platform_admin' => ! $revoke])->save();

        $this->info(($revoke ? 'Revoked' : 'Granted')." platform-admin status for {$user->email}.");

        return self::SUCCESS;
    }
}
