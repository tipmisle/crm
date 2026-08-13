<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('display_name')->nullable();
            $table->string('handle')->nullable();
            $table->string('status')->default('not_connected');
            $table->timestamp('connected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
