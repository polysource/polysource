<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

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
}
