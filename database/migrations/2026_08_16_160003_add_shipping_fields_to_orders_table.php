<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal shipping fields for the "Obvesti stranko" order notification
 * feature (App\Http\Controllers\OrderNotificationController). No carrier
 * integration — these are just what a user types into the "Pošiljka je
 * bila poslana" modal, kept on the Order so a later notification can
 * prefill them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('customer_notes');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('tracking_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'tracking_url', 'shipped_at']);
        });
    }
};
