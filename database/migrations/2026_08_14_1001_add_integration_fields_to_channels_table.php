<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->foreignId('integration_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->string('external_account_id')->nullable()->after('type');
            $table->text('access_token')->nullable()->after('metadata');
            $table->timestamp('last_synced_at')->nullable()->after('connected_at');

            // The composite unique(workspace_id, type) is the only index backing
            // the workspace_id foreign key, so add a plain index to keep the FK
            // supported before dropping it in the same statement batch.
            $table->index('workspace_id', 'channels_workspace_id_index');
            $table->dropUnique(['workspace_id', 'type']);
            $table->unique(['workspace_id', 'type', 'external_account_id'], 'channels_workspace_type_account_unique');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique('channels_workspace_type_account_unique');
            $table->unique(['workspace_id', 'type']);
            $table->dropIndex('channels_workspace_id_index');
            $table->dropConstrainedForeignId('integration_id');
            $table->dropColumn(['external_account_id', 'access_token', 'last_synced_at']);
        });
    }
};
