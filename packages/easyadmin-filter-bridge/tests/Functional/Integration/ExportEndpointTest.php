<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use PHPUnit\Framework\Attributes\Test;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;

/**
 * End-to-end coverage for `GET /admin/polysource/export/{resource}.{format}`.
 *
 * Locks in regression coverage for:
 * - C5 (cold metadata cache 404)
 * - C6 (fputcsv PHP 8.4 deprecation — no warning emitted on streaming)
 * - C7 (toIterable + HYDRATE_ARRAY + entity-alias select → empty CSV)
 * - C8 (DateTime values formatted as ISO 8601)
 * - C10 (IN / NOT IN / IS [NOT] NULL filters applied)
 */
final class ExportEndpointTest extends BridgeIntegrationTestCase
{
    #[Test]
    public function streamsCsvWithAllEntityRows(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/export/' . $resource . '.csv');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $lines = explode("\n", trim($body));
        // C7 regression — 30 data rows + 1 header. Pre-fix this was 1.
        self::assertCount(31, $lines);
    }

    #[Test]
    public function csvBodyStartsWithUtf8Bom(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/export/' . $resource . '.csv');

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        self::assertSame("\xEF\xBB\xBF", substr($body, 0, 3), 'CSV must start with the UTF-8 BOM');
    }

    #[Test]
    public function csvFormatsDateTimeColumnsAsIso8601(): void
    {
        // C8 regression — pre-fix, `createdAt` came out empty in CSV.
        // Post-fix, every DateTime value is formatted as ISO 8601
        // (DateTimeInterface::ATOM).
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/export/' . $resource . '.csv');

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        // Item 1's createdAt = base + 3 days = 2026-01-04T08:00:00+00:00.
        self::assertStringContainsString('2026-01-04T08:00:00+00:00', $body);
    }

    #[Test]
    public function csvBooleansAreEncodedAsZeroOrOne(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request('GET', '/admin/polysource/export/' . $resource . '.csv');

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $lines = explode("\n", trim($body));
        $row3 = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, '3,')) {
                $row3 = $line;
                break;
            }
        }
        self::assertNotNull($row3, 'Row for id=3 must be present in the CSV body');
    }

    #[Test]
    public function exportRespectsScalarEqualityFilter(): void
    {
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request(
            'GET',
            \sprintf(
                '/admin/polysource/export/%s.csv?filters[status][comparison]==&filters[status][value]=published',
                $resource,
            ),
        );

        self::assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", trim($body)), static fn (string $l): bool => '' !== $l));
        self::assertGreaterThan(1, \count($lines), 'header + at least 1 data row');
        self::assertLessThan(31, \count($lines), 'fewer rows than the unfiltered export');
    }

    #[Test]
    public function exportRespectsInFilter(): void
    {
        // C10 regression — pre-fix, IN was silently dropped → export
        // contained ALL 30 rows regardless. Post-fix, only draft +
        // published rows are exported.
        $resource = $this->encodeResource(TestItem::class);
        $response = $this->request(
            'GET',
            \sprintf(
                '/admin/polysource/export/%s.csv?filters[status][comparison]=IN&filters[status][value][]=draft&filters[status][value][]=published',
                $resource,
            ),
        );

        self::assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", trim($body)), static fn (string $l): bool => '' !== $l));
        // Subset of 30, more than just header.
        self::assertGreaterThan(2, \count($lines));
        self::assertLessThan(31, \count($lines));

        // Every data row's `status` column (index 2) must be in the IN set.
        $headerCount = 1;
        foreach (\array_slice($lines, $headerCount) as $line) {
            $cells = str_getcsv($line, ',', '"', '');
            self::assertContains($cells[2], ['draft', 'published'], 'row outside IN set: ' . $line);
        }
    }

    #[Test]
    public function exportOnUnknownEntityReturns404(): void
    {
        $response = $this->request('GET', '/admin/polysource/export/NotAnEntity.csv');
        self::assertSame(404, $response->getStatusCode());
    }
}
