<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prerequisite for the sensitive-data encryption migration
 * (App\Console\Commands\EncryptSensitiveData / docs/data-security.md):
 *
 * - follow_ups.note was varchar(255). Laravel's encrypted-cast ciphertext
 *   (base64 JSON envelope) for even a short plaintext string comfortably
 *   exceeds 255 bytes, so it must widen to TEXT before any row is encrypted
 *   or the ciphertext would be silently truncated by MySQL.
 * - messages.metadata was a native JSON column. Ciphertext is a plain
 *   string, not valid JSON, so MySQL would reject writes once the
 *   'encrypted:array' cast is applied. Converting to TEXT keeps the same
 *   PHP-level array shape (via the cast) while allowing ciphertext storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->text('note')->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->text('metadata')->nullable()->change();
        });
    }

    /**
     * Intentionally irreversible once any row has been encrypted (which, in
     * the documented deployment order, happens shortly after this
     * migration runs — see docs/pre-launch-security.md "Production
     * encryption cutover"). Shrinking follow_ups.note back to varchar(255)
     * would silently truncate ciphertext, and converting messages.metadata
     * back to a native JSON column would fail outright (ciphertext isn't
     * valid JSON) or corrupt data. There is no safe automatic down() for
     * this migration — restore from the pre-cutover database backup
     * instead. Do not attempt `php artisan migrate:rollback` on this
     * migration in an environment that has run
     * security:encrypt-sensitive-data or deployed the encrypted casts.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'This migration cannot be safely reversed once data has been encrypted. '.
            'Restore from the pre-cutover database backup instead — see '.
            'docs/pre-launch-security.md "Production encryption cutover" for the exact procedure.'
        );
    }
};
