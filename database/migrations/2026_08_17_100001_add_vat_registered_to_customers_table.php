<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a business customer is VAT-registered — only meaningful when
 * is_business is true. Drives whether "Davčna številka" (the customer's
 * VAT/tax id, shown on invoices issued to them) is relevant on the
 * customer form — see EditCustomerModal.vue and Customers/Create.vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('vat_registered')->default(false)->after('is_business');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('vat_registered');
        });
    }
};
