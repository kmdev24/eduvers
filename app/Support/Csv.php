<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download that opens cleanly in Excel (UTF-8 BOM) and is
 * protected against spreadsheet formula injection.
 */
class Csv
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel detects UTF-8

            fputcsv($out, array_map([self::class, 'clean'], $headers), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'clean'], $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Prefix values that a spreadsheet would treat as a formula. */
    public static function clean(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
