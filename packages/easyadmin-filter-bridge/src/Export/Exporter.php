<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Export;

use BackedEnum;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Streaming exporter — turns an iterable of row arrays into a CSV
 * or XLSX response. Memory-bounded: rows are written to the
 * response body as they're produced, no full in-memory buffer.
 *
 * Two backends:
 *
 *   - **CSV** — uses PHP's built-in `fputcsv`, no external dep.
 *     Always available.
 *
 *   - **XLSX** — uses `openspout/openspout` (optional dep declared
 *     in `composer.json suggest`). The method throws a clear
 *     `\RuntimeException` when openspout isn't installed so hosts
 *     get an actionable error instead of a class-not-found fatal.
 *
 * Filter-aware export (only rows matching the current `?filters[...]`
 * URL slice) is deferred to v0.4.0 — see roadmap. The current v0.3.0
 * exporter takes a host-provided iterable, so hosts who need filtered
 * output today can construct it themselves (Doctrine
 * QueryBuilder::iterate() is the canonical pattern).
 *
 * @since 0.3.0
 */
final class Exporter
{
    /**
     * Build a streamed CSV response.
     *
     * @param iterable<array<int|string, mixed>> $rows  source rows; each yielded array is a CSV record. Values are coerced to string via the helper below — strings/Stringables go through unchanged, null becomes empty, bool becomes "0"/"1", arrays are JSON-encoded
     * @param list<string>                       $headers column headers written as the first record
     */
    public function streamCsv(
        iterable $rows,
        array $headers,
        string $filename = 'export.csv',
    ): StreamedResponse {
        $response = new StreamedResponse(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');
            if (false === $handle) {
                return;
            }

            // BOM so Excel opens UTF-8 CSVs correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->stringify(...), $row));
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            \sprintf('attachment; filename="%s"', $this->sanitizeFilename($filename)),
        );

        return $response;
    }

    /**
     * Build a streamed XLSX response. Throws when openspout isn't
     * installed — the host should either `composer require
     * openspout/openspout` or only expose CSV in their UI.
     *
     * @param iterable<array<int|string, mixed>> $rows
     * @param list<string>                       $headers
     */
    public function streamXlsx(
        iterable $rows,
        array $headers,
        string $filename = 'export.xlsx',
    ): StreamedResponse {
        if (!class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
            throw new RuntimeException('XLSX export requires the `openspout/openspout` package. Install it via `composer require openspout/openspout` or expose only CSV export in your UI.');
        }

        $response = new StreamedResponse(function () use ($rows, $headers): void {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');

            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(
                    array_values(array_map($this->stringify(...), $row)),
                ));
            }

            $writer->close();
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->headers->set(
            'Content-Disposition',
            \sprintf('attachment; filename="%s"', $this->sanitizeFilename($filename)),
        );

        return $response;
    }

    private function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        if (\is_array($value)) {
            $json = json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

            return false === $json ? '' : $json;
        }
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return '';
    }

    private function sanitizeFilename(string $name): string
    {
        // Strip anything that could break the Content-Disposition
        // header (CR/LF, double quotes). Keep it ASCII-only via a
        // conservative pass.
        $clean = preg_replace('/[\\\\\\/\\x00-\\x1F\\x7F"]+/', '_', $name) ?? '';

        return '' !== $clean ? $clean : 'export';
    }
}
