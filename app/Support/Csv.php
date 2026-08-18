<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Small Excel-friendly CSV streaming helper for operational data exports
 * (Orders/Appointments "Izvozi CSV") — never persisted to disk, generated
 * on the fly per request. UTF-8 BOM + semicolon delimiter so it opens
 * correctly in Excel under Slovenian regional settings (whose decimal
 * separator is a comma, so Excel's own CSV import expects `;` as the list
 * separator, not `,`). Also defuses spreadsheet formula injection: any
 * cell starting with =, +, -, or @ is prefixed with a leading apostrophe,
 * which Excel/Sheets/LibreOffice all treat as "force this cell to text"
 * rather than evaluating it.
 */
class Csv
{
    private const DELIMITER = ';';

    private const FORMULA_TRIGGER_CHARS = ['=', '+', '-', '@'];

    /**
     * @param  string[]  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function streamDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, self::DELIMITER);

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::sanitizeCell(...), $row), self::DELIMITER);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function sanitizeCell(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $value = (string) ($value ?? '');

        if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGER_CHARS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
