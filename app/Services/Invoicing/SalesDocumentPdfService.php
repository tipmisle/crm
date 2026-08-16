<?php

namespace App\Services\Invoicing;

use Barryvdh\DomPDF\Facade\Pdf;
use DataLinx\PhpUpnQrGenerator\UPNQR;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders a SalesDocument (or a synthetic settings-page preview) to PDF
 * bytes. Never writes to disk itself — the caller decides whether the
 * result is persisted (real issuance) or streamed and discarded (preview),
 * see Invoicing\SalesDocumentController and Settings\InvoiceSettingsController.
 */
class SalesDocumentPdfService
{
    public function render(array $data): string
    {
        return Pdf::loadView('pdf.sales-document', $data)->output();
    }

    /**
     * Builds the official ZBS UPN QR payload via datalinx/php-upn-qr-generator
     * (spec-compliant field order + checksum — not hand-rolled) and renders
     * it to a QR PNG via endroid/qr-code's GD-backed PngWriter (no Imagick
     * dependency). Returns a base64 data URI ready for an <img> tag, or null
     * if generation fails for any reason — a QR problem must never block
     * issuing the underlying document, whose IBAN/reference/amount are
     * always shown as plain text regardless.
     */
    public function renderUpnQr(array $payload): ?string
    {
        try {
            $upn = new UPNQR;
            $upn->setRecipientIban($payload['recipient_iban']);
            $upn->setRecipientCity($payload['recipient_city']);
            $upn->setRecipientName($payload['recipient_name'] ?? null);
            $upn->setRecipientStreetAddress($payload['recipient_street_address'] ?? null);
            $upn->setRecipientReference($payload['recipient_reference'] ?? null);
            $upn->setAmount($payload['amount']);
            $upn->setPaymentPurpose($payload['payment_purpose']);
            $upn->setPaymentDueDate($payload['payment_due_date'] ?? null);

            $qrPayload = $upn->serializeContents();

            // UPN QR is specified against ISO-8859-2 (Latin-2, covers
            // Slovenian diacritics) — matches the encoding the reference
            // library itself uses for its own file writer.
            $builder = new Builder(
                writer: new PngWriter,
                data: $qrPayload,
                encoding: new Encoding('ISO-8859-2'),
                size: 240,
                margin: 8,
            );

            return $builder->build()->getDataUri();
        } catch (Throwable $e) {
            Log::warning('invoicing.upn_qr.render_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
