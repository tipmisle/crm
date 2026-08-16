<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('demo_variant');
            $table->timestamp('scheduled_deletion_at')->nullable()->after('deletion_requested_at');

            // Used by workspaces:purge-expired's WHERE is_demo = false AND
            // scheduled_deletion_at <= now() query.
            $table->index(['is_demo', 'scheduled_deletion_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['is_demo', 'scheduled_deletion_at']);
            $table->dropColumn(['deletion_requested_at', 'scheduled_deletion_at']);
        });
    }
};
