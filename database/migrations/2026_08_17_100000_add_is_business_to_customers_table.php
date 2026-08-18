<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this customer is a business (invoiced under a company name/tax
 * number) or a private person. Drives whether the "Davčna številka" field
 * is relevant on the customer form — see EditCustomerModal.vue and
 * Customers/Create.vue. Defaults to false so existing customers are
 * treated as private persons unless explicitly marked otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_business')->default(false)->after('tax_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_business');
        });
    }
};
