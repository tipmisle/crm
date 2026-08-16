<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier's subscriptions table, hand-adapted from the vendor stub
 * (vendor/laravel/cashier/database/migrations/2019_05_03_000002_create_subscriptions_table.php)
 * — the FK column is `workspace_id`, not the stock `user_id`, matching
 * Cashier::useCustomerModel(Workspace::class): Cashier's own Billable/
 * Subscription relations resolve the FK dynamically via the billable
 * model's Eloquent getForeignKey() ("workspace_id" for Workspace), so this
 * column name is not cosmetic — it must match exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'stripe_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
