<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type');
            $table->string('external_id')->nullable();
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'channel_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
    }
};
