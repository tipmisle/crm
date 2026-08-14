<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_identities', function (Blueprint $table) {
            $table->unique(
                ['workspace_id', 'channel_type', 'external_id'],
                'customer_identities_workspace_channel_external_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_identities', function (Blueprint $table) {
            $table->dropUnique('customer_identities_workspace_channel_external_unique');
        });
    }
};
