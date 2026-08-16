<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe record for Stripe webhook deliveries. Cashier's own webhook
 * handling is idempotent for the DB syncs it performs itself, but this
 * app's ADDED side effects (audit log rows, the BillingPaymentSucceeded
 * event) are not — Stripe delivers at-least-once and retries, so every
 * custom handler in StripeWebhookController inserts-or-skips here first.
 * No payload column, deliberately — identifiers only, see docs/billing.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
