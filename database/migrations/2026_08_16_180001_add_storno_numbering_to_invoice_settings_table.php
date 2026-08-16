<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storno/correction documents get their OWN sequential numbering series,
 * independent from invoice_next_number/proforma_next_number — the same
 * established pattern this workspace already uses to keep proforma and
 * invoice sequences from colliding (see
 * App\Services\Invoicing\SalesDocumentNumberingService).
 *
 * This is the conservative reading of Slovenian invoicing practice: a
 * correction document (dobropis/storno) must be clearly identifiable as
 * such, must reference the original invoice's number and issue date (see
 * SalesDocument::corrects_document_id), and must carry its own gapless,
 * never-reused sequential number — but no ZDDV-1/FURS source found during
 * research mandates sharing the invoice counter itself, so a distinctly
 * prefixed separate series avoids ever conflating the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->string('storno_prefix')->default('S-'.now()->format('Y').'-')->after('proforma_next_number');
            $table->unsignedInteger('storno_next_number')->default(1)->after('storno_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn(['storno_prefix', 'storno_next_number']);
        });
    }
};
