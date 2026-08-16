<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Pre-deploy gate: fails non-zero if required legal/provider config
 * (config/legal.php) is missing. Meant to be run manually or from a CI/
 * deploy step before shipping the public legal pages to production — see
 * docs/legal-compliance.md. Not scheduled; this is a one-shot check, not a
 * recurring job.
 */
class CheckLegalConfig extends Command
{
    protected $signature = 'legal:check';

    protected $description = 'Report missing required legal/provider configuration before production launch.';

    /**
     * Blocking — a public legal page or registration flow would be
     * factually wrong or blank without these.
     */
    private const REQUIRED = [
        'company_name',
        'registered_address',
        'registration_number',
        'tax_number',
        'legal_email',
        'terms_version',
        'dpa_version',
        'privacy_version',
        'cookie_version',
    ];

    /**
     * Advisory — desirable but not launch-blocking on their own.
     */
    private const ADVISORY = [
        'dpo_contact',
        'competent_court',
    ];

    public function handle(): int
    {
        $missing = [];

        foreach (self::REQUIRED as $key) {
            if (blank(config("legal.{$key}"))) {
                $missing[] = $key;
                $this->error("Missing required legal config: legal.{$key}");
            }
        }

        if (config('legal.vat_registered') && blank(config('legal.vat_number'))) {
            $missing[] = 'vat_number';
            $this->error('Missing required legal config: legal.vat_number (legal.vat_registered is true)');
        }

        foreach (self::ADVISORY as $key) {
            if (blank(config("legal.{$key}"))) {
                $this->warn("Advisory: legal.{$key} is not set.");
            }
        }

        if (! empty($missing)) {
            $this->error(count($missing).' required legal config value(s) missing. See .env.example "LEGAL_*" keys.');

            return self::FAILURE;
        }

        $this->info('legal:check passed — required legal config is present.');

        return self::SUCCESS;
    }
}
