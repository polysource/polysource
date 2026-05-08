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
    public function test_writes_header_then_rows(): void
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $written = $exporter->write([$this->record(actorLabel: 'alice')], $stream);

        $this->assertSame(1, $written);

        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        $this->assertIsArray($header);
        $this->assertSame('id', $header[0]);
        $this->assertSame('actor_label', $header[3]);
        $this->assertIsArray($row);
        $this->assertSame('alice', $row[3]);
    }

    /**
     * @dataProvider formulaInjectionPayloads
     */
    public function test_prefixes_formula_chars_so_spreadsheets_treat_them_as_literals(string $payload): void
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $exporter->write([$this->record(actorLabel: $payload)], $stream);

        rewind($stream);
        fgetcsv($stream, escape: ''); // header
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        $this->assertIsArray($row);
        $cell = (string) $row[3];
        $this->assertStringStartsWith("'", $cell, 'cell must be prefixed so Excel/Calc do not parse it as a formula');
        $this->assertSame("'" . $payload, $cell);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formulaInjectionPayloads(): iterable
    {
        yield 'equals'    => ['=cmd|\' /C calc\'!A0'];
        yield 'plus'      => ['+1+1+cmd'];
        yield 'minus'     => ['-2+3+cmd'];
        yield 'at-sign'   => ['@SUM(A1:A2)'];
        yield 'tab'       => ["\t=1+1"];
        yield 'cr'        => ["\r=1+1"];
    }

    public function test_safe_values_are_left_untouched(): void
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        $exporter = new AuditCsvExporter();
        $exporter->write([$this->record(actorLabel: 'alice@acme.com')], $stream);

        rewind($stream);
        fgetcsv($stream, escape: ''); // header
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        $this->assertIsArray($row);
        $this->assertSame('alice@acme.com', $row[3], 'mid-string @ must not trigger sanitisation');
    }

    private function record(string $actorLabel): AuditEntryRecord
    {
        $record = new AuditEntryRecord();
        $record->id = '0192f0c0-0000-0000-0000-000000000001';
        $record->occurredAt = new DateTimeImmutable('2026-05-08T10:00:00', new DateTimeZone('UTC'));
        $record->actorId = 'user-1';
        $record->actorLabel = $actorLabel;
        $record->resourceName = 'failed-messages';
        $record->actionName = 'retry';
        $record->outcome = 'success';
        $record->message = null;
        $record->durationMs = 12;
        $record->recordIdsJson = '["1"]';
        $record->contextJson = '{}';

        return $record;
    }
}
