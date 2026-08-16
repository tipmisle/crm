<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether unit prices entered in the invoicing UI already include VAT
 * ("Vnesene cene vključujejo DDV"). Defaults true because Order.price /
 * Appointment.price are the customer-facing final price — an invoice
 * generated from a workspace's existing orders must default to VAT being
 * extracted from that price, not added on top of it. See
 * App\Services\Invoicing\SalesDocumentCalculationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->boolean('prices_include_vat')->default(true)->after('vat_registered');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn('prices_include_vat');
        });
    }
};
