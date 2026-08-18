<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace-editable appointment statuses, replacing the fixed
 * App\Enums\AppointmentStatus as the source of truth for
 * appointments.status (the enum survives only as the default seed values
 * used by App\Services\WorkspaceStatusDefaults and demo seeders — see that
 * class). `key` is generated once and never changes on rename, so
 * appointments.status stays valid across a relabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('color', 7);
            $table->string('bg', 7);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_no_show')->default(false);
            $table->boolean('is_refunded')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
            $table->index(['workspace_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_statuses');
    }
};
