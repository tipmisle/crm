<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('primary_channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
