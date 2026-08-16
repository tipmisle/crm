<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk_path');
            $table->string('status')->default('ready');
            $table->timestamp('expires_at');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_exports');
    }
};
