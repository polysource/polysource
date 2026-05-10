<?php

declare(strict_types=1);

namespace Polysource\Adapter\Flysystem\Tests\Unit\DataSource;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\Pagination;
use RuntimeException;

final class FlysystemDataSourceTest extends TestCase
{
    private Filesystem $filesystem;
    private FlysystemDataSource $source;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystem->write('uploads/2026/05/invoice-001.pdf', 'PDF-1');
        $this->filesystem->write('uploads/2026/05/invoice-002.pdf', 'PDF-2');
        $this->filesystem->write('uploads/2026/04/receipt.pdf', 'old');
        $this->filesystem->write('uploads/notes.txt', 'plain notes');
        $this->filesystem->write('outside/ignored.txt', 'should not appear');

        $this->source = new FlysystemDataSource($this->filesystem, pathPrefix: 'uploads', recursive: true);
    }

    public function testSearchListsFilesUnderPrefix(): void
    {
        $items = $this->source->search(new DataQuery('files'))->asArray();

        $paths = array_map(static fn ($r): string => (string) $r->identifier, $items);
        sort($paths);

        // Only the 4 entries under uploads/, plus their containing directories.
        // We don't assert directories specifically — Flysystem's recursive
        // listing surfaces them, the data source carries `isDirectory=true`.
        self::assertContains('2026/05/invoice-001.pdf', $paths);
        self::assertContains('2026/05/invoice-002.pdf', $paths);
        self::assertContains('2026/04/receipt.pdf', $paths);
        self::assertContains('notes.txt', $paths);
        self::assertNotContains('outside/ignored.txt', $paths);
    }

    public function testSearchExposesMimeAndSize(): void
    {
        $items = $this->source->search(new DataQuery('files'))->asArray();
        $byPath = [];
        foreach ($items as $r) {
            $byPath[(string) $r->identifier] = $r;
        }

        $invoice = $byPath['2026/05/invoice-001.pdf'] ?? null;
        self::assertNotNull($invoice);
        self::assertSame('pdf', $invoice->properties['extension']);
        self::assertSame(5, $invoice->properties['sizeBytes']);
        self::assertFalse($invoice->properties['isDirectory']);
    }

    public function testFindReturnsRecord(): void
    {
        $record = $this->source->find('2026/05/invoice-001.pdf');
        self::assertNotNull($record);
        self::assertSame('invoice-001.pdf', $record->properties['fileName']);
        self::assertSame('uploads/2026/05/invoice-001.pdf', $record->properties['absolutePath']);
        self::assertSame(5, $record->properties['sizeBytes']);
    }

    public function testFindReturnsNullForUnknownPath(): void
    {
        self::assertNull($this->source->find('does-not-exist.pdf'));
    }

    public function testCountAlwaysNull(): void
    {
        self::assertNull($this->source->count(new DataQuery('files')));
    }

    public function testFilterByExtension(): void
    {
        $query = (new DataQuery('files'))
            ->withFilter('extension', new FilterCriterion('extension', 'eq', 'txt'));

        $items = $this->source->search($query)->asArray();
        $paths = array_map(static fn ($r): string => (string) $r->identifier, $items);
        self::assertSame(['notes.txt'], $paths);
    }

    public function testSearchTextFiltersOnFileName(): void
    {
        $query = (new DataQuery('files'))->withSearchText('invoice');
        $items = $this->source->search($query)->asArray();

        self::assertCount(2, $items);
    }

    public function testCreateWritesAndReturnsRecord(): void
    {
        $record = $this->source->create(new DataPayload([
            'path' => 'new-file.txt',
            'contents' => 'hello world',
        ]));

        self::assertSame('new-file.txt', $record->identifier);
        self::assertSame(11, $record->properties['sizeBytes']);
        self::assertSame('hello world', $this->filesystem->read('uploads/new-file.txt'));
    }

    public function testUpdateOverwritesExistingFile(): void
    {
        $record = $this->source->update('notes.txt', new DataPayload([
            'contents' => 'updated content',
        ]));

        self::assertSame('updated content', $this->filesystem->read('uploads/notes.txt'));
        self::assertSame(15, $record->properties['sizeBytes']);
    }

    public function testUpdateRejectsMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->update('does-not-exist.pdf', new DataPayload(['contents' => 'x']));
    }

    public function testCreateRejectsMissingContents(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->create(new DataPayload(['path' => 'broken.txt']));
    }

    public function testCreateRejectsMissingPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->create(new DataPayload(['contents' => 'x']));
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->source->delete('notes.txt');
        self::assertNull($this->source->find('notes.txt'));

        // second call must not throw
        $this->source->delete('notes.txt');
    }

    public function testPaginationOffsetLimitExposesTotal(): void
    {
        // Add a bunch of files to overflow one page.
        for ($i = 0; $i < 30; ++$i) {
            $this->filesystem->write("uploads/bulk/file-{$i}.txt", "content {$i}");
        }

        $page = $this->source->search(
            (new DataQuery('files'))->withPagination(new Pagination(offset: 0, limit: 10))
        );
        self::assertCount(10, $page->asArray());
        // Switched from cursor to offset/limit pagination — total is
        // now the materialised file count, not null.
        self::assertGreaterThanOrEqual(30, $page->total);
        self::assertNull($page->nextCursor);
    }

    public function testNoPathPrefixUsesBareFilesystem(): void
    {
        $source = new FlysystemDataSource($this->filesystem, pathPrefix: '', recursive: false);
        $items = $source->search(new DataQuery('files'))->asArray();

        $paths = array_map(static fn ($r): string => (string) $r->identifier, $items);
        // outside/ now in scope (recursive=false, so only top-level entries).
        self::assertContains('uploads', $paths);
        self::assertContains('outside', $paths);
    }
}
