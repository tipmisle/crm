<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this workspace takes deposits ("are") on orders at all. When
 * false, the Order "Ara" field is hidden — see Orders/Show.vue and
 * OrderController. Defaults to true so existing workspaces keep current
 * behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('accepts_deposit')->default(true)->after('appointments_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('accepts_deposit');
        });
    }
};
