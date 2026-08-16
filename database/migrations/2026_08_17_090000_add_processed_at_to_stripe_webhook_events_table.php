<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "delivery claimed" from "handler succeeded" so a handler that
 * throws after the row is inserted doesn't permanently block Stripe's
 * retries — see StripeWebhookController::withDedupe().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
