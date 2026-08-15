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

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('note')->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->json('metadata')->nullable()->change();
        });
    }
};
