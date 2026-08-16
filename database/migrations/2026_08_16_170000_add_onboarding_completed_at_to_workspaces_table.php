<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Null means "show the /onboarding first-run flow". Existing (non-demo)
 * workspaces are backfilled to completed below so current users are never
 * suddenly dropped into onboarding — only newly created real workspaces
 * start incomplete. Demo workspaces bypass onboarding at runtime (see
 * Workspace::needsOnboarding()), so they're left untouched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('accepts_deposit');
        });

        DB::table('workspaces')->where('is_demo', false)->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
