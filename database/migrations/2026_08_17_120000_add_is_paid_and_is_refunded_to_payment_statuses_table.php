<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a workspace payment status as the "paid" or "refunded" state —
 * mirrors order_statuses.is_completed/is_refunded. Every workspace must
 * always have exactly one status flagged is_paid and one flagged
 * is_refunded (enforced in Settings\PaymentStatusController), alongside
 * the existing is_default ("Neplačano").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_statuses', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('is_outstanding');
            $table->boolean('is_refunded')->default(false)->after('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('payment_statuses', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'is_refunded']);
        });
    }
};
