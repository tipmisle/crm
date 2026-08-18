<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subject is not SQL-searched/filtered anywhere (the admin UI only
 * filters bug reports/feature requests by status) — so per the project's
 * encryption policy it's encrypted like message, not left plaintext just
 * because it happens to be short. Widened to `text` first: ciphertext for
 * a ~150-char plaintext subject comfortably exceeds a varchar(255).
 * Existing plaintext rows are migrated by `security:encrypt-sensitive-
 * data` (see App\Console\Commands\EncryptSensitiveData) — this migration
 * only changes the column type, never touches row data itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bug_reports', function (Blueprint $table) {
            $table->text('subject')->change();
        });

        Schema::table('feature_requests', function (Blueprint $table) {
            $table->text('subject')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bug_reports', function (Blueprint $table) {
            $table->string('subject')->change();
        });

        Schema::table('feature_requests', function (Blueprint $table) {
            $table->string('subject')->change();
        });
    }
};
