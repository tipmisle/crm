<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CustomerErasureService needs to clear an OrderNote's free-text body when
 * the customer it's about is erased (same as Message.body, Customer.notes,
 * etc.) — the column must allow null for that, matching every other
 * encrypted free-text column in the app. See App\Services\CustomerErasureService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
        });
    }
};
