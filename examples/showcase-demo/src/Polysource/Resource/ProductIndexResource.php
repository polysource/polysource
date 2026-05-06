<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use Polysource\Adapter\Meilisearch\Client\MeilisearchIndexInterface;
use Polysource\Adapter\Meilisearch\DataSource\MeilisearchDataSource;
use Polysource\Adapter\Meilisearch\Resource\MeilisearchIndexResource;
use Polysource\Bundle\Attribute\AsResource;

/**
 * Read+write view of the Meilisearch `shopco-products` index. Demonstrates
 * the search-first DataSource: the bridge translates Polysource filter
 * criteria to Meilisearch's filter expression syntax with strict
 * property-whitelisting (anti-injection).
 */
#[AsResource]
final class ProductIndexResource extends MeilisearchIndexResource
{
    public function __construct(MeilisearchIndexInterface $index)
    {
        parent::__construct(
            dataSource: new MeilisearchDataSource(
                index: $index,
                primaryKey: 'id',
            ),
            slug: 'search-index',
            label: 'Search index',
            permission: 'POLYSOURCE_SEARCH_INDEX_VIEW',
        );
    }
}
