<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Export;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Export\AuditCsvExporter;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;

final class AuditCsvExporterTest extends TestCase
{
    public function testWritesHeaderThenRows(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $written = $exporter->write([$this->record(actorLabel: 'alice')], $stream);

        self::assertSame(1, $written);

        rewind($stream);
        // Skip the UTF-8 BOM emitted before the header row.
        self::assertSame("\xEF\xBB\xBF", fread($stream, 3));
        $header = fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        self::assertIsArray($header);
        self::assertSame('id', $header[0]);
        self::assertSame('actor_label', $header[3]);
        self::assertIsArray($row);
        self::assertSame('alice', $row[3]);
    }

    public function testEmitsUtf8BomBeforeHeaderSoSpreadsheetsDetectEncoding(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        (new AuditCsvExporter())->write([$this->record(actorLabel: 'alice')], $stream);

        rewind($stream);
        self::assertSame("\xEF\xBB\xBF", fread($stream, 3), 'UTF-8 BOM must be the first three bytes of the file');
    }

    public function testCollapsesNewlinesInMessageColumn(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $message = "shippingAddress: '123 Main St\nApt 4B\nNYC' → '=cmd|...'";
        $exporter = new AuditCsvExporter();
        $exporter->write([$this->record(actorLabel: 'alice', message: $message)], $stream);

        rewind($stream);
        fread($stream, 3); // skip BOM
        fgetcsv($stream, escape: ''); // header
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        self::assertIsArray($row);
        $messageCell = (string) $row[7];
        self::assertStringNotContainsString("\n", $messageCell, 'embedded newlines must be collapsed for grep-friendly CSV');
        self::assertStringNotContainsString("\r", $messageCell);
        self::assertStringContainsString('123 Main St Apt 4B NYC', $messageCell);
    }

    /**
     * @dataProvider formulaInjectionPayloads
     */
    public function testPrefixesFormulaCharsSoSpreadsheetsTreatThemAsLiterals(string $payload): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $exporter->write([$this->record(actorLabel: $payload)], $stream);

        rewind($stream);
        fread($stream, 3); // skip BOM
        fgetcsv($stream, escape: ''); // header
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        self::assertIsArray($row);
        $cell = (string) $row[3];
        self::assertStringStartsWith("'", $cell, 'cell must be prefixed so Excel/Calc do not parse it as a formula');
        self::assertSame("'" . $payload, $cell);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formulaInjectionPayloads(): iterable
    {
        yield 'equals' => ['=cmd|\' /C calc\'!A0'];
        yield 'plus' => ['+1+1+cmd'];
        yield 'minus' => ['-2+3+cmd'];
        yield 'at-sign' => ['@SUM(A1:A2)'];
        yield 'tab' => ["\t=1+1"];
        yield 'cr' => ["\r=1+1"];
    }

    public function testSafeValuesAreLeftUntouched(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $exporter->write([$this->record(actorLabel: 'alice@acme.com')], $stream);

        rewind($stream);
        fread($stream, 3); // skip BOM
        fgetcsv($stream, escape: ''); // header
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        self::assertIsArray($row);
        self::assertSame('alice@acme.com', $row[3], 'mid-string @ must not trigger sanitisation');
    }

    private function record(string $actorLabel, ?string $message = null): AuditEntryRecord
    {
        $record = new AuditEntryRecord();
        $record->id = '0192f0c0-0000-0000-0000-000000000001';
        $record->occurredAt = new DateTimeImmutable('2026-05-08T10:00:00', new DateTimeZone('UTC'));
        $record->actorId = 'user-1';
        $record->actorLabel = $actorLabel;
        $record->resourceName = 'failed-messages';
        $record->actionName = 'retry';
        $record->outcome = 'success';
        $record->message = $message;
        $record->durationMs = 12;
        $record->recordIdsJson = '["1"]';
        $record->contextJson = '{}';

        return $record;
    }
}
