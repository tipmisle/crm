<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace-editable payment statuses — shared by both Order and Appointment
 * (payment states like unpaid/deposit/paid apply the same way to both, so
 * this is one list, not duplicated). See order_statuses migration for the
 * same `key`-stability rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('color', 7);
            $table->string('bg', 7);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_deposit_default')->default(false);
            $table->boolean('is_outstanding')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
            $table->index(['workspace_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_statuses');
    }
};
