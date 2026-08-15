<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-time (but safely re-runnable) backfill that encrypts existing
 * plaintext values in the high-sensitivity free-text columns listed in
 * docs/data-security.md, using the exact ciphertext format Laravel's
 * 'encrypted' / 'encrypted:array' Eloquent casts expect.
 *
 * Deliberately operates on raw DB rows (Illuminate\Support\Facades\DB, not
 * Eloquent models) — this command is meant to run BEFORE the corresponding
 * model casts are deployed, so there is no cast to accidentally trigger a
 * decrypt-of-plaintext error while migrating.
 *
 * Idempotent: for every value, this command first attempts
 * Crypt::decryptString() (json_decode for the `json`-shaped columns). If
 * that succeeds, the value is already ciphertext and is left untouched —
 * running this command twice (or resuming after an interruption) never
 * double-encrypts a row.
 *
 * Safe on large tables: uses DB::table(...)->orderBy('id')->chunkById(),
 * never loads an unbounded result set into memory.
 *
 * See docs/data-security.md "Part 6 — Safe existing-data migration" and
 * the deployment order in that document before running this in production.
 */
class EncryptSensitiveData extends Command
{
    protected $signature = 'security:encrypt-sensitive-data
        {--chunk=500 : Number of rows to process per chunk}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Encrypt existing plaintext values in high-sensitivity free-text columns (idempotent, chunked).';

    /**
     * `nullable` must match the actual DB column nullability. For nullable
     * columns, a legacy empty string is normalized to NULL (an empty note
     * is semantically "no note", and NULL sidesteps ever needing to decrypt
     * an empty-string ciphertext). order_notes.body and follow_ups.note are
     * NOT NULL columns — a legacy '' there is encrypted as an empty string
     * instead, which round-trips through the 'encrypted' cast correctly.
     *
     * @var array<int, array{table: string, column: string, json: bool, nullable: bool}>
     */
    private const TARGETS = [
        ['table' => 'messages', 'column' => 'body', 'json' => false, 'nullable' => true],
        ['table' => 'messages', 'column' => 'metadata', 'json' => true, 'nullable' => true],
        ['table' => 'conversations', 'column' => 'last_message_preview', 'json' => false, 'nullable' => true],
        ['table' => 'customers', 'column' => 'notes', 'json' => false, 'nullable' => true],
        ['table' => 'orders', 'column' => 'description', 'json' => false, 'nullable' => true],
        ['table' => 'orders', 'column' => 'internal_notes', 'json' => false, 'nullable' => true],
        ['table' => 'orders', 'column' => 'customer_notes', 'json' => false, 'nullable' => true],
        ['table' => 'appointments', 'column' => 'description', 'json' => false, 'nullable' => true],
        ['table' => 'appointments', 'column' => 'internal_notes', 'json' => false, 'nullable' => true],
        ['table' => 'appointments', 'column' => 'customer_notes', 'json' => false, 'nullable' => true],
        ['table' => 'order_notes', 'column' => 'body', 'json' => false, 'nullable' => false],
        ['table' => 'follow_ups', 'column' => 'note', 'json' => false, 'nullable' => false],
    ];

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no rows will be modified.');
        }

        $totals = ['encrypted' => 0, 'normalized_empty' => 0, 'already_encrypted' => 0, 'errors' => 0];

        foreach (self::TARGETS as $target) {
            $this->line("→ {$target['table']}.{$target['column']}");

            $counts = $this->migrateColumn($target['table'], $target['column'], $target['json'], $target['nullable'], $chunkSize, $dryRun);

            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }

            $this->line("  encrypted={$counts['encrypted']} normalized_empty={$counts['normalized_empty']} already_encrypted={$counts['already_encrypted']} errors={$counts['errors']}");
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. encrypted=%d normalized_empty=%d already_encrypted=%d errors=%d',
            $totals['encrypted'],
            $totals['normalized_empty'],
            $totals['already_encrypted'],
            $totals['errors'],
        ));

        return $totals['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{encrypted: int, normalized_empty: int, already_encrypted: int, errors: int}
     */
    private function migrateColumn(string $table, string $column, bool $isJson, bool $nullable, int $chunkSize, bool $dryRun): array
    {
        $counts = ['encrypted' => 0, 'normalized_empty' => 0, 'already_encrypted' => 0, 'errors' => 0];

        // Deliberately does NOT exclude '' here (a previous version of this
        // command did, which left legacy empty-string rows plaintext
        // forever — reading '' through Crypt::decryptString() once the
        // 'encrypted' cast was deployed threw DecryptException). Every
        // non-null value, including '', is now visited and either
        // normalized or encrypted below.
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($table, $column, $isJson, $nullable, $dryRun, &$counts) {
                foreach ($rows as $row) {
                    $value = $row->{$column};

                    if ($value === '' && $nullable) {
                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update([$column => null]);
                        }

                        $counts['normalized_empty']++;

                        continue;
                    }

                    if ($this->looksAlreadyEncrypted($value, $isJson)) {
                        $counts['already_encrypted']++;

                        continue;
                    }

                    try {
                        $ciphertext = $isJson
                            ? Crypt::encryptString(json_encode(json_decode($value, true), JSON_THROW_ON_ERROR))
                            : Crypt::encryptString($value);
                    } catch (\Throwable $e) {
                        $counts['errors']++;
                        $this->error("  row {$row->id}: failed to encrypt ({$e->getMessage()})");

                        continue;
                    }

                    if (! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update([$column => $ciphertext]);
                    }

                    $counts['encrypted']++;
                }
            });

        return $counts;
    }

    /**
     * Detects whether a raw column value is already in Laravel's encrypted
     * format, so re-running this command is a safe no-op for rows it has
     * already migrated (or that were written post-cutover by application
     * code already using the 'encrypted' cast).
     */
    private function looksAlreadyEncrypted(string $value, bool $isJson): bool
    {
        try {
            $decrypted = Crypt::decryptString($value);
        } catch (DecryptException) {
            return false;
        }

        if (! $isJson) {
            return true;
        }

        json_decode($decrypted);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
