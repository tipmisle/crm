<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->decimal('default_price', 10, 2)->nullable();
            $table->decimal('default_deposit_amount', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
