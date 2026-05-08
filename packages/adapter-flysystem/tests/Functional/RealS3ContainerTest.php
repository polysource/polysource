<?php

declare(strict_types=1);

namespace Polysource\Adapter\Flysystem\Tests\Functional;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Throwable;

/**
 * Wire-level test against a REAL MinIO container (S3-compatible).
 *
 * Skipped when `POLYSOURCE_REAL_S3_*` env vars are missing. CI's
 * `e2e` job sets them from the showcase compose stack.
 *
 * Catches integration drift the in-memory adapter hides:
 * AsyncAws S3 client API changes, real bucket semantics around
 * listObjects pagination, real object metadata round-trip
 * (mime, size, lastModified).
 *
 * @group real-container
 */
final class RealS3ContainerTest extends TestCase
{
    private const PREFIX = 'polysource-e2e/';

    private FlysystemDataSource $dataSource;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $endpoint = getenv('POLYSOURCE_REAL_S3_ENDPOINT');
        $bucket = getenv('POLYSOURCE_REAL_S3_BUCKET');
        $accessKey = getenv('POLYSOURCE_REAL_S3_ACCESS_KEY');
        $secretKey = getenv('POLYSOURCE_REAL_S3_SECRET_KEY');
        if ($endpoint === false || $bucket === false || $accessKey === false || $secretKey === false) {
            self::markTestSkipped('Set POLYSOURCE_REAL_S3_* env vars to run against a real S3-compatible container.');
        }

        /** @phpstan-ignore-next-line argument.type — AsyncAws Configuration's PHPStan type is overly strict; these keys are the documented public surface. */
        $client = new S3Client([
            'endpoint' => $endpoint,
            'pathStyleEndpoint' => true, // MinIO requires path-style.
            'region' => 'us-east-1',
            'accessKeyId' => $accessKey,
            'accessKeySecret' => $secretKey,
        ]);

        $adapter = new AsyncAwsS3Adapter($client, $bucket);
        $this->filesystem = new Filesystem($adapter);

        // Clean any pre-existing files under our prefix. Iterate +
        // delete one-by-one because `deleteDirectory()` semantics on
        // S3-compatible backends (MinIO via AsyncAws) can silently
        // miss files when the "directory" was previously left in a
        // partial state, leaving leaked test data that breaks the
        // host's admin pages (the showcase forbids "/" in S3 ids,
        // so leaked paths from prior runs surface a 500).
        try {
            $contents = $this->filesystem->listContents(rtrim(self::PREFIX, '/'), true);
            foreach ($contents as $entry) {
                if ($entry->isFile()) {
                    $this->filesystem->delete($entry->path());
                }
            }
        } catch (Throwable) {
            // best-effort
        }

        $this->dataSource = new FlysystemDataSource(
            filesystem: $this->filesystem,
            pathPrefix: self::PREFIX,
            recursive: true,
        );
    }

    public function testCreateAndFindRoundTripThroughRealS3(): void
    {
        $record = $this->dataSource->create(new DataPayload([
            'path' => 'invoices/2026/001.txt',
            'contents' => 'Invoice body.',
        ]));

        self::assertNotEmpty($record->identifier);

        $loaded = $this->dataSource->find($record->identifier);
        self::assertNotNull($loaded);
        // FlysystemDataSource exposes file metadata, not contents — the
        // admin UI streams contents on demand from a separate route.
        self::assertSame('invoices/2026/001.txt', $loaded->properties['path'] ?? null);
        self::assertSame('001.txt', $loaded->properties['fileName'] ?? null);
        self::assertSame('txt', $loaded->properties['extension'] ?? null);
    }

    public function testListReturnsObjectsUnderThePrefix(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->dataSource->create(new DataPayload([
                'path' => \sprintf('files/file-%d.txt', $i),
                'contents' => "content $i",
            ]));
        }

        $page = $this->dataSource->search(new DataQuery('s3-test'));
        $items = [...$page->items];

        self::assertCount(5, $items);
        // MinIO returns alphabetic order — assert deterministic shape.
        $firstId = $items[0]->identifier;
        self::assertStringStartsWith('files/file-', (string) $firstId);
    }

    public function testDeleteRemovesObjectFromTheRealBucket(): void
    {
        $this->dataSource->create(new DataPayload([
            'path' => 'doomed.txt',
            'contents' => 'gone soon',
        ]));
        self::assertNotNull($this->dataSource->find('doomed.txt'));

        $this->dataSource->delete('doomed.txt');

        self::assertNull($this->dataSource->find('doomed.txt'));
    }

    public function testMetadataIsExposedOnTheRecord(): void
    {
        $this->dataSource->create(new DataPayload([
            'path' => 'image.png',
            'contents' => str_repeat('X', 4096),
        ]));

        $loaded = $this->dataSource->find('image.png');
        self::assertNotNull($loaded);

        // FlysystemDataSource exposes size on every loaded record so
        // the admin UI can render a column without re-fetching metadata.
        self::assertSame(4096, $loaded->properties['sizeBytes'] ?? null);
    }
}
