<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use App\Polysource\Filter\GenericFilter;
use League\Flysystem\FilesystemOperator;
use Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource;
use Polysource\Adapter\Flysystem\Resource\FlysystemResource;
use Polysource\Bundle\Attribute\AsResource;

/**
 * Browse the ShopCo S3 bucket on MinIO. Holds invoice PDFs and
 * product photos. Recursive listing with offset-emulated pagination
 * (Flysystem listings don't support cursor-based paging natively).
 */
#[AsResource]
final class S3FileResource extends FlysystemResource
{
    public function __construct(FilesystemOperator $filesystem)
    {
        parent::__construct(
            dataSource: new FlysystemDataSource(
                filesystem: $filesystem,
                pathPrefix: '',
                recursive: true,
            ),
            slug: 's3-files',
            label: 'S3 files',
            permission: 'POLYSOURCE_S3_VIEW',
        );
    }

    public function configureFields(string $page): iterable
    {
        yield Field::new('fileName', 'File name')->asText();
        yield Field::new('extension', 'Type')->asText();
        yield Field::new('mimeType', 'MIME type')->asText();
        yield Field::new('sizeBytes', 'Size (bytes)')->asText();

        if ($page === 'detail') {
            yield Field::new('path', 'Path')->asText();
            yield Field::new('absolutePath', 'Absolute path')->asText();
        }
    }

    public function configureFilters(): iterable
    {
        // FlysystemDataSource exposes path/extension/mimeType/sizeBytes
        // on every listing. Filters apply client-side (the listing
        // is paginated server-side first, then filtered).
        yield GenericFilter::text('fileName', 'File name');
        yield GenericFilter::exact('extension', 'Extension');
        yield GenericFilter::text('mimeType', 'MIME type');
        yield GenericFilter::numeric('sizeBytes', 'Size (bytes)');
    }
}
