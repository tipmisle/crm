<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company's legal name, distinct from full_name (the contact person
 * at that company). Only relevant when is_business is true — see
 * EditCustomerModal.vue and Customers/Create.vue. full_name still names
 * the person to correspond with; company_name is what invoices/documents
 * are issued to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('is_business');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};
