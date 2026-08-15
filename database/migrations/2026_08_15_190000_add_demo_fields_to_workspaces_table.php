<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('appointments_enabled');
            $table->timestamp('demo_expires_at')->nullable()->after('is_demo');
            $table->string('demo_variant')->nullable()->after('demo_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['is_demo', 'demo_expires_at', 'demo_variant']);
        });
    }
};
