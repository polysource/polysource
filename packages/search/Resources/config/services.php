<?php

declare(strict_types=1);

use Polysource\Search\Controller\SearchController;
use Polysource\Search\Search\SearchAggregator;
use Polysource\Search\Twig\SearchExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* Aggregator — fan-out across every tagged provider.
       Hosts ship custom providers tagged `polysource.search.provider`. */
    $services->set(SearchAggregator::class)
        ->arg('$providers', tagged_iterator('polysource.search.provider'))
        ->public();

    /* Twig extension — palette helper. */
    $services->set(SearchExtension::class)
        ->arg('$twig', service('twig'));

    /* Controller — JSON endpoint at /admin/search. */
    $services->set(SearchController::class)
        ->arg('$aggregator', service(SearchAggregator::class))
        ->public()
        ->tag('controller.service_arguments');
};
