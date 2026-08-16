<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Predračun/Račun documents — both natively-issued (source=issued, numbered
 * from invoice_settings) and externally-created uploads (source=external,
 * e.g. Minimax/Pantheon/Apollo) share this table so the Order "Dokumenti"
 * section can render one unified list. External rows never populate
 * prefix/sequence_number — see external_document_number, kept in a
 * separate column so the two numbering spaces can never be confused.
 *
 * Issued documents are immutable snapshots: seller/customer/line-item/
 * payment data is copied in at issue time (the *_snapshot columns) so later
 * edits to the Order, Customer, or InvoiceSettings never alter a document
 * that has already been issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // proforma | invoice | other
            $table->string('source'); // issued | external
            $table->string('prefix')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('document_number')->nullable();
            $table->string('external_document_number')->nullable();
            $table->date('issued_at');
            $table->date('service_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('vat_registered')->default(false);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('seller_snapshot')->nullable();
            $table->text('customer_snapshot')->nullable();
            $table->text('line_items_snapshot')->nullable();
            $table->text('payment_snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'type', 'prefix', 'sequence_number']);
            $table->index(['workspace_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_documents');
    }
};
