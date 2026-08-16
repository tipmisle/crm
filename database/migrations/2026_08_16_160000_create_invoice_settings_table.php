<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per workspace — the "RAČUNI IN PLAČILA" settings for the
 * customer-invoicing module (App\Models\InvoiceSettings). Separate from
 * Beležka's own Stripe/Cashier subscription billing (subscriptions table) —
 * this is billing BY the workspace TO their own customers. See
 * App\Services\Invoicing\SalesDocumentNumberingService for why the two
 * *_next_number counters live here and are locked at issuance time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_number')->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('iban')->nullable();
            $table->string('bank_name')->nullable();
            $table->unsignedInteger('default_payment_deadline_days')->default(8);
            $table->string('place_of_issue')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('vat_exempt_note')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('invoice_prefix')->default(now()->format('Y').'-');
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->string('proforma_prefix')->default('P-'.now()->format('Y').'-');
            $table->unsignedInteger('proforma_next_number')->default(1);
            $table->timestamps();

            $table->unique('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
