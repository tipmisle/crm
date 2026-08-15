<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data already copied to catalog_items (see the
        // migrate_services_to_catalog_items migration) and
        // appointments.service_id already repointed there.
        Schema::dropIfExists('services');
    }

    public function down(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_duration_minutes')->default(60);
            $table->decimal('default_price', 10, 2)->nullable();
            $table->decimal('default_deposit_amount', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'active']);
        });
    }
};
