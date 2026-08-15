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
     * @var array<int, array{table: string, column: string, json: bool}>
     */
    private const TARGETS = [
        ['table' => 'messages', 'column' => 'body', 'json' => false],
        ['table' => 'messages', 'column' => 'metadata', 'json' => true],
        ['table' => 'conversations', 'column' => 'last_message_preview', 'json' => false],
        ['table' => 'customers', 'column' => 'notes', 'json' => false],
        ['table' => 'orders', 'column' => 'description', 'json' => false],
        ['table' => 'orders', 'column' => 'internal_notes', 'json' => false],
        ['table' => 'orders', 'column' => 'customer_notes', 'json' => false],
        ['table' => 'appointments', 'column' => 'description', 'json' => false],
        ['table' => 'appointments', 'column' => 'internal_notes', 'json' => false],
        ['table' => 'appointments', 'column' => 'customer_notes', 'json' => false],
        ['table' => 'order_notes', 'column' => 'body', 'json' => false],
        ['table' => 'follow_ups', 'column' => 'note', 'json' => false],
    ];

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no rows will be modified.');
        }

        $totals = ['encrypted' => 0, 'already_encrypted' => 0, 'null_or_empty' => 0, 'errors' => 0];

        foreach (self::TARGETS as $target) {
            $this->line("→ {$target['table']}.{$target['column']}");

            $counts = $this->migrateColumn($target['table'], $target['column'], $target['json'], $chunkSize, $dryRun);

            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }

            $this->line("  encrypted={$counts['encrypted']} already_encrypted={$counts['already_encrypted']} skipped_null={$counts['null_or_empty']} errors={$counts['errors']}");
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. encrypted=%d already_encrypted=%d skipped_null=%d errors=%d',
            $totals['encrypted'],
            $totals['already_encrypted'],
            $totals['null_or_empty'],
            $totals['errors'],
        ));

        return $totals['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{encrypted: int, already_encrypted: int, null_or_empty: int, errors: int}
     */
    private function migrateColumn(string $table, string $column, bool $isJson, int $chunkSize, bool $dryRun): array
    {
        $counts = ['encrypted' => 0, 'already_encrypted' => 0, 'null_or_empty' => 0, 'errors' => 0];

        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($table, $column, $isJson, $dryRun, &$counts) {
                foreach ($rows as $row) {
                    $value = $row->{$column};

                    if ($value === null || $value === '') {
                        $counts['null_or_empty']++;

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
