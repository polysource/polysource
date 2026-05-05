<?php

declare(strict_types=1);

namespace Polysource\Adapter\Flysystem\DataSource;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use RuntimeException;

/**
 * Read+write data source over a {@see FilesystemOperator} —
 * Flysystem's storage abstraction. Backs **any** Flysystem adapter:
 * S3, local disk, Azure Blob, GCS, FTP, in-memory.
 *
 * Each record represents one file (directories are listed with
 * `_isDirectory = true` for navigation but cannot be opened in the
 * current v0.1 surface — listing inside a directory is achieved by
 * passing the directory path to a sibling resource configured with
 * the deeper prefix).
 *
 * Cf. ADR-002 — `count()` returns `null`. Most cloud blob stores
 * cannot count without enumerating; the UI uses cursor pagination
 * over Flysystem's `listContents()` iterator.
 *
 * Identifier convention: the relative path from the configured
 * `pathPrefix`. So if `pathPrefix='uploads/'` and the file lives at
 * `uploads/2026/05/invoice.pdf`, the record's identifier is
 * `2026/05/invoice.pdf`.
 *
 * Filtering in v0.1 is **client-side**: search-text matches against
 * the file name, `mimeType` / `extension` filter operators apply
 * after listing. Larger filesystems should ship a custom data
 * source backed by a search index.
 */
final class FlysystemDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly string $pathPrefix = '',
        private readonly bool $recursive = true,
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        $pagination = $query->pagination;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;
        $offset = null === $pagination ? 0 : $pagination->offset;

        try {
            /** @var iterable<StorageAttributes> $listing */
            $listing = $this->filesystem->listContents($this->pathPrefix, $this->recursive);
        } catch (FilesystemException) {
            return new DataPage([], null);
        }

        $records = [];
        $skipped = 0;
        $hasMore = false;

        foreach ($listing as $attributes) {
            $record = $this->toDataRecord($attributes);
            if (!self::matchesFilters($record, $query)) {
                continue;
            }

            if ($skipped < $offset) {
                ++$skipped;

                continue;
            }

            if (\count($records) >= $limit) {
                $hasMore = true;

                break;
            }

            $records[] = $record;
        }

        return new DataPage(
            items: $records,
            total: null,
            nextCursor: $hasMore ? (string) ($offset + $limit) : null,
            prevCursor: $offset > 0 ? (string) max(0, $offset - $limit) : null,
        );
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $relativePath = (string) $identifier;
        $absolutePath = $this->absolutePath($relativePath);

        try {
            if (!$this->filesystem->fileExists($absolutePath)) {
                return null;
            }

            return new DataRecord($relativePath, [
                'path' => $relativePath,
                'absolutePath' => $absolutePath,
                'fileName' => basename($relativePath),
                'extension' => self::extensionOrNull($relativePath),
                'mimeType' => $this->safeMimeType($absolutePath),
                'sizeBytes' => $this->safeFileSize($absolutePath),
                'lastModified' => $this->safeLastModified($absolutePath),
                'isDirectory' => false,
            ]);
        } catch (FilesystemException) {
            return null;
        }
    }

    public function count(DataQuery $query): ?int
    {
        unset($query);

        return null;
    }

    public function create(DataPayload $payload): DataRecord
    {
        $relativePath = self::extractRelativePath($payload);
        $contents = $payload->get('contents');
        if (null === $contents) {
            throw new RuntimeException('FlysystemDataSource: payload must carry "contents" (string or stream resource).');
        }

        $absolutePath = $this->absolutePath($relativePath);
        try {
            if (\is_resource($contents)) {
                $this->filesystem->writeStream($absolutePath, $contents);
            } else {
                $this->filesystem->write($absolutePath, self::asString($contents));
            }
        } catch (FilesystemException $e) {
            throw new RuntimeException(\sprintf('FlysystemDataSource: failed to write "%s": %s', $relativePath, $e->getMessage()), 0, $e);
        }

        $record = $this->find($relativePath);
        if (null === $record) {
            throw new RuntimeException(\sprintf('FlysystemDataSource: write succeeded but read-back failed for "%s".', $relativePath));
        }

        return $record;
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        // For Flysystem, update == overwrite. We require the file to
        // exist first so callers can't silently mis-spell a path
        // and create a new file under it.
        $relativePath = (string) $identifier;
        $absolutePath = $this->absolutePath($relativePath);

        try {
            if (!$this->filesystem->fileExists($absolutePath)) {
                throw new RuntimeException(\sprintf('FlysystemDataSource: cannot update "%s" — file does not exist.', $relativePath));
            }
        } catch (FilesystemException $e) {
            throw new RuntimeException(\sprintf('FlysystemDataSource: existence check failed for "%s": %s', $relativePath, $e->getMessage()), 0, $e);
        }

        return $this->create($payload->with('path', $relativePath));
    }

    public function delete(int|string $identifier): void
    {
        $absolutePath = $this->absolutePath((string) $identifier);
        try {
            if ($this->filesystem->fileExists($absolutePath)) {
                $this->filesystem->delete($absolutePath);
            }
        } catch (FilesystemException) {
            // Idempotent — same convention as DoctrineDataSource::delete
            // and the audit purge command.
        }
    }

    private function toDataRecord(StorageAttributes $attributes): DataRecord
    {
        $absolutePath = $attributes->path();
        $relativePath = $this->relativePath($absolutePath);
        $isDirectory = $attributes->isDir();

        return new DataRecord($relativePath, [
            'path' => $relativePath,
            'absolutePath' => $absolutePath,
            'fileName' => basename($relativePath),
            'extension' => $isDirectory ? null : self::extensionOrNull($relativePath),
            'mimeType' => $isDirectory ? null : $this->safeMimeType($absolutePath),
            'sizeBytes' => $isDirectory ? null : $this->safeFileSize($absolutePath),
            'lastModified' => $this->safeLastModified($absolutePath),
            'isDirectory' => $isDirectory,
        ], $attributes);
    }

    private function absolutePath(string $relativePath): string
    {
        if ('' === $this->pathPrefix) {
            return ltrim($relativePath, '/');
        }

        return rtrim($this->pathPrefix, '/') . '/' . ltrim($relativePath, '/');
    }

    private function relativePath(string $absolutePath): string
    {
        if ('' === $this->pathPrefix) {
            return $absolutePath;
        }

        $prefix = rtrim($this->pathPrefix, '/') . '/';
        if (str_starts_with($absolutePath, $prefix)) {
            return substr($absolutePath, \strlen($prefix));
        }

        return $absolutePath;
    }

    private function safeMimeType(string $absolutePath): ?string
    {
        try {
            return $this->filesystem->mimeType($absolutePath);
        } catch (FilesystemException) {
            return null;
        }
    }

    private function safeFileSize(string $absolutePath): ?int
    {
        try {
            return $this->filesystem->fileSize($absolutePath);
        } catch (FilesystemException) {
            return null;
        }
    }

    private function safeLastModified(string $absolutePath): ?int
    {
        try {
            return $this->filesystem->lastModified($absolutePath);
        } catch (FilesystemException) {
            return null;
        }
    }

    private static function matchesFilters(DataRecord $record, DataQuery $query): bool
    {
        foreach ($query->filters as $criterion) {
            $value = $record->get($criterion->property);
            $matches = match ($criterion->operator) {
                'eq' => self::asString($value) === self::asString($criterion->value),
                'in' => \is_array($criterion->value) && \in_array(self::asString($value), array_map(self::asString(...), $criterion->value), true),
                'like' => \is_string($value) && \is_string($criterion->value) && false !== stripos($value, $criterion->value),
                default => true,
            };
            if (!$matches) {
                return false;
            }
        }

        if (null !== $query->searchText && '' !== $query->searchText) {
            $needle = strtolower($query->searchText);
            $name = self::asString($record->get('fileName'));
            if (!str_contains(strtolower($name), $needle)) {
                return false;
            }
        }

        return true;
    }

    private static function extractRelativePath(DataPayload $payload): string
    {
        $path = $payload->get('path');
        if (!\is_string($path) || '' === $path) {
            throw new RuntimeException('FlysystemDataSource: payload must carry a non-empty "path" property.');
        }

        return $path;
    }

    private static function extensionOrNull(string $path): ?string
    {
        $ext = pathinfo($path, \PATHINFO_EXTENSION);

        return '' === $ext ? null : $ext;
    }

    private static function asString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }
}
