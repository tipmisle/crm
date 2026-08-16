<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1 issued-document correction support (App\Models\SalesDocument):
 *
 * - status: 'issued' (default) | 'cancelled' (voided proforma, never sent
 *   as an active document again) | 'reversed' (invoice that has had a
 *   storno correction issued against it).
 * - cancelled_at / cancellation_reason: when a proforma is cancelled,
 *   cancelled_at is set (no reason required). When an invoice is
 *   storno'd, cancelled_at is set on the ORIGINAL and cancellation_reason
 *   is set on the storno CORRECTION document (the required reason the
 *   user gave for issuing it) — see
 *   App\Http\Controllers\Invoicing\SalesDocumentCorrectionController.
 * - corrects_document_id: set only on a storno-type SalesDocument,
 *   pointing at the original invoice it reverses. The unique index
 *   guarantees at the DB level that an invoice can never be storno'd
 *   twice, even under concurrent requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('status')->default('issued')->after('type');
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('corrects_document_id')->nullable()->after('appointment_id')
                ->constrained('sales_documents')->nullOnDelete();
            $table->unique('corrects_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            // MySQL refuses to drop a unique index that a foreign key still
            // depends on, so the FK (which drops its own index) must go
            // first — dropUnique() afterwards would then have nothing left
            // to drop.
            $table->dropConstrainedForeignId('corrects_document_id');
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
