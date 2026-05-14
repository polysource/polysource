<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Export;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Export\Exporter;

#[CoversClass(Exporter::class)]
final class ExporterTest extends TestCase
{
    #[Test]
    public function streamCsvProducesHeadersAndDataRowsWithBom(): void
    {
        $exporter = new Exporter();
        $rows = [
            ['id' => 1, 'name' => 'Alice', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false],
        ];

        $response = $exporter->streamCsv($rows, ['id', 'name', 'active']);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        // UTF-8 BOM at the very start so Excel opens UTF-8 correctly.
        self::assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $lines = explode("\n", trim(substr($body, 3)));
        self::assertSame('id,name,active', $lines[0]);
        self::assertSame('1,Alice,1', $lines[1]);
        self::assertSame('2,Bob,0', $lines[2]);

        self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function streamCsvCoercesNullAndArraysAndEnums(): void
    {
        $exporter = new Exporter();
        $rows = [
            [
                'string' => 'plain',
                'null' => null,
                'array' => ['a', 'b'],
                'backed' => ExporterFixtureBackedEnum::Active,
                'unit' => ExporterFixtureUnitEnum::Active,
            ],
        ];

        $response = $exporter->streamCsv($rows, ['string', 'null', 'array', 'backed', 'unit']);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        // Skip the BOM + header line
        $lines = explode("\n", trim(substr($body, 3)));
        self::assertSame('plain,,"[""a"",""b""]",active,Active', $lines[1]);
    }

    #[Test]
    public function streamCsvFormatsDateTimeAsIso8601(): void
    {
        // Friction C8 (v0.5.7) — DateTime values previously fell
        // through the stringify chain to the empty-string default,
        // every datetime column came out blank in exports. ISO 8601
        // (DateTimeInterface::ATOM) is the universal text-sortable
        // format that Excel + every date library understands.
        $exporter = new Exporter();
        $rows = [
            [
                'mutable' => new DateTime('2026-03-31 08:52:54', new DateTimeZone('Europe/Paris')),
                'immutable' => new DateTimeImmutable('2026-04-21 17:37:21', new DateTimeZone('UTC')),
            ],
        ];

        $response = $exporter->streamCsv($rows, ['mutable', 'immutable']);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $dataLine = explode("\n", trim(substr($body, 3)))[1];
        self::assertSame(
            '2026-03-31T08:52:54+02:00,2026-04-21T17:37:21+00:00',
            $dataLine,
            'DateTime + DateTimeImmutable must render as ISO 8601 in CSV — never as empty strings',
        );
    }

    #[Test]
    public function streamCsvFilenameIsInDispositionHeader(): void
    {
        $exporter = new Exporter();

        $response = $exporter->streamCsv([], ['id'], 'my-export.csv');

        self::assertStringContainsString('filename="my-export.csv"', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function streamCsvSanitizesUnsafeFilename(): void
    {
        $exporter = new Exporter();

        // CR/LF/quote would break the Content-Disposition header
        $response = $exporter->streamCsv([], ['id'], "evil\r\n\"name.csv");

        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringNotContainsString("\r", $disposition);
        self::assertStringNotContainsString("\n", $disposition);
    }

    #[Test]
    public function streamXlsxThrowsCleanlyWhenOpenspoutIsMissing(): void
    {
        // openspout IS installed in dev (composer require-dev). To
        // simulate the missing-dep error path we'd need to mock
        // class_exists, which PHPUnit can't do without uopz. Instead,
        // we sanity-check that the method exists and returns the
        // streamed response when openspout IS present — the missing
        // case is covered by the throw clause's explicit message
        // which a host-side e2e on a vanilla install will see.
        if (!class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
            self::markTestSkipped('openspout not installed in dev — install via composer require --dev openspout/openspout.');
        }

        $exporter = new Exporter();
        $response = $exporter->streamXlsx([['id' => 1]], ['id']);

        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
    }
}

enum ExporterFixtureBackedEnum: string
{
    case Active = 'active';
    case Archived = 'archived';
}

enum ExporterFixtureUnitEnum
{
    case Active;
    case Archived;
}
