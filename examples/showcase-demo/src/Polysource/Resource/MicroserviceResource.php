<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use Polysource\Adapter\Http\DataSource\HttpDataSource;
use Polysource\Adapter\Http\Pagination\PageNumberPaginationStrategy;
use Polysource\Adapter\Http\Resource\HttpResource;
use Polysource\Bundle\Attribute\AsResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Microservices probe: fan out to the 3 internal services (payments,
 * shipping, notifications) simulated by WireMock and surface their
 * health status as a Polysource resource.
 *
 * The HttpDataSource hits `/microservices` (list) and
 * `/microservices/{id}` (detail) on the WireMock-backed base URL.
 */
#[AsResource]
final class MicroserviceResource extends HttpResource
{
    public function __construct(
        #[Autowire(service: 'app.http_client.microservices')]
        HttpClientInterface $client,
    ) {
        parent::__construct(
            dataSource: new HttpDataSource(
                client: $client,
                baseUri: '/admin/microservices',
                pagination: new PageNumberPaginationStrategy(
                    pageQueryParam: 'page',
                    perPageQueryParam: 'size',
                    itemsKey: 'data',
                    totalKey: 'meta.total',
                ),
            ),
            slug: 'microservices',
            label: 'Microservices',
            permission: 'POLYSOURCE_MICROSERVICES_VIEW',
        );
    }

    public function configureFields(string $page): iterable
    {
        yield Field::new('id', 'ID')->asId();
        yield Field::new('name', 'Service')->asText();
        yield Field::new('status', 'Status')->asText();
        yield Field::new('uptime', 'Uptime')->asText();
        yield Field::new('queueDepth', 'Queue depth')->asText();

        if ($page === 'detail') {
            yield Field::new('endpoint', 'Endpoint')->asText();
            yield Field::new('lastIncident', 'Last incident')->asText();
        }
    }
}
