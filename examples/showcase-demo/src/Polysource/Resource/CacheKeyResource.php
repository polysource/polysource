<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use App\Polysource\Filter\GenericFilter;
use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;
use Polysource\Adapter\Redis\Resource\RedisHashResource;
use Polysource\Bundle\Attribute\AsResource;

/**
 * Browse the Redis cache layer used by ShopCo for product / cart
 * caching. Prefix `shopco:cache:` scopes the resource so it does not
 * spill into the Symfony cache pool keys (which sit under another
 * prefix used by the framework cache).
 *
 * Read+write — ops can drop a stale cache entry without exec'ing
 * `redis-cli del` on the box.
 */
#[AsResource]
final class CacheKeyResource extends RedisHashResource
{
    public function __construct(RedisHashClientInterface $client)
    {
        parent::__construct(
            dataSource: new RedisHashDataSource($client, 'shopco:cache:'),
            slug: 'cache-keys',
            label: 'Redis cache',
            permission: 'POLYSOURCE_CACHE_VIEW',
        );
    }

    public function configureFields(string $page): iterable
    {
        // Redis hash properties depend on the seeded shape:
        //   shopco:cache:product:hot:NNNN  → {id, name, priceCents, stock, cachedAt}
        //   shopco:cache:cart:HEX          → {id, items, totalCents, updatedAt}
        // Showing the union — empty cells render as "—" via generic.html.twig.
        yield Field::new('id', 'Key ID')->asId();
        yield Field::new('name', 'Name')->asText();
        yield Field::new('priceCents', 'Price (¢)')->asText();
        yield Field::new('stock', 'Stock')->asText();
        yield Field::new('items', 'Items')->asText();
        yield Field::new('totalCents', 'Total (¢)')->asText();
        yield Field::new('cachedAt', 'Cached at')->asText();
        yield Field::new('updatedAt', 'Updated at')->asText();
    }

    public function configureFilters(): iterable
    {
        // The Redis adapter exposes the hash key + a few standard
        // hash fields. Filters submitted from the modal arrive as
        // FilterCriterion in the DataQuery; the data source applies
        // a client-side `LIKE` on the matching hash field value
        // (cf. RedisHashDataSource::matchesCriterion).
        yield GenericFilter::text('id', 'Key ID');
        yield GenericFilter::text('name', 'Product name');
        yield GenericFilter::numeric('priceCents', 'Price (cents)');
        yield GenericFilter::numeric('stock', 'Stock');
    }
}
