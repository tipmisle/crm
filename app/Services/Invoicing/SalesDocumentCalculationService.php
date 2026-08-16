<?php

namespace App\Services\Invoicing;

/**
 * The single source of truth for turning raw line items into the
 * subtotal/VAT/total a SalesDocument (invoice or proforma) issues with —
 * used by SalesDocumentController::store() for real issuance,
 * InvoiceSettingsController::preview() for the sample PDF, and mirrored
 * client-side by Invoicing/Create.vue for the live on-screen total.
 *
 * Order.price / Appointment.price are the customer-facing FINAL price, so
 * when $pricesIncludeVat is true the entered unit_price is treated as
 * gross and VAT is extracted from it — never added on top — so an order
 * priced at 80 € produces an invoice that still totals 80 €.
 *
 * All arithmetic is done in integer cents so that, for every VAT-rate
 * group, net + vat === gross exactly — no rounding leftover to
 * reconcile — which is what guarantees tax-breakdown + net === total.
 */
class SalesDocumentCalculationService
{
    /**
     * @param  array<int, array{description: string, quantity: float|int|string, unit?: ?string, unit_price: float|int|string, vat_rate?: float|int|string|null}>  $lineItems
     * @return array{
     *     lines: array<int, array{description: string, quantity: float, unit: ?string, unit_price: float, vat_rate: float, net: float, vat: float, gross: float, line_total: float}>,
     *     tax_breakdown: array<int, array{vat_rate: float, net: float, vat: float, gross: float}>,
     *     subtotal: float,
     *     vat_total: float,
     *     total: float,
     * }
     */
    public function calculate(array $lineItems, bool $vatRegistered, bool $pricesIncludeVat): array
    {
        $lines = [];
        $breakdownCents = [];
        $subtotalCents = 0;
        $vatTotalCents = 0;
        $totalCents = 0;

        foreach ($lineItems as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $vatRate = $vatRegistered ? (float) ($item['vat_rate'] ?? 0) : 0.0;

            $lineAmountCents = (int) round($quantity * $unitPrice * 100);

            if ($vatRate > 0) {
                if ($pricesIncludeVat) {
                    $grossCents = $lineAmountCents;
                    $netCents = (int) round($grossCents / (1 + $vatRate / 100));
                    $vatCents = $grossCents - $netCents;
                } else {
                    $netCents = $lineAmountCents;
                    $vatCents = (int) round($netCents * $vatRate / 100);
                    $grossCents = $netCents + $vatCents;
                }
            } else {
                $netCents = $lineAmountCents;
                $vatCents = 0;
                $grossCents = $lineAmountCents;
            }

            $lines[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'net' => $netCents / 100,
                'vat' => $vatCents / 100,
                'gross' => $grossCents / 100,
                'line_total' => $grossCents / 100,
            ];

            $breakdownKey = number_format($vatRate, 4, '.', '');
            $breakdownCents[$breakdownKey] ??= ['vat_rate' => $vatRate, 'net' => 0, 'vat' => 0, 'gross' => 0];
            $breakdownCents[$breakdownKey]['net'] += $netCents;
            $breakdownCents[$breakdownKey]['vat'] += $vatCents;
            $breakdownCents[$breakdownKey]['gross'] += $grossCents;

            $subtotalCents += $netCents;
            $vatTotalCents += $vatCents;
            $totalCents += $grossCents;
        }

        $taxBreakdown = array_values(array_map(fn (array $row) => [
            'vat_rate' => $row['vat_rate'],
            'net' => $row['net'] / 100,
            'vat' => $row['vat'] / 100,
            'gross' => $row['gross'] / 100,
        ], $breakdownCents));

        return [
            'lines' => $lines,
            'tax_breakdown' => $taxBreakdown,
            'subtotal' => $subtotalCents / 100,
            'vat_total' => $vatTotalCents / 100,
            'total' => $totalCents / 100,
        ];
    }
}
