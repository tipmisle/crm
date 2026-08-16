<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional invoice-recipient details — populated either by the customer
 * record itself or, per document, via "Shrani podatke pri stranki" when
 * issuing a predračun/račun (see SalesDocumentController). Never required
 * for a Customer to exist; a document can also carry recipient details of
 * its own in customer_snapshot without ever touching these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('phone');
            $table->string('postal_code')->nullable()->after('address_line');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country')->nullable()->after('city');
            $table->string('tax_number')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['address_line', 'postal_code', 'city', 'country', 'tax_number']);
        });
    }
};
