<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Storage\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_filter_url_tokens` table.
 *
 * @since 0.5.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_filter_url_tokens')]
#[ORM\Index(name: 'polysource_filter_url_tokens_resource_idx', columns: ['resource_name'])]
#[ORM\Index(name: 'polysource_filter_url_tokens_created_idx', columns: ['created_at'])]
class FilterUrlTokenRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    public string $token;

    #[ORM\Column(name: 'resource_name', type: 'string', length: 128)]
    public string $resourceName;

    /** JSON-encoded `?filters[...]` slice. */
    #[ORM\Column(name: 'filters_slice_json', type: 'text')]
    public string $filtersSliceJson;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;
}
