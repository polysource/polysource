<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Polysource\BulkAsync\Action\CancelBulkJobAction;
use Polysource\BulkAsync\Controller\ProgressController;
use Polysource\BulkAsync\DataSource\BulkJobDataSource;
use Polysource\BulkAsync\Dispatcher\AsyncBulkActionDispatcher;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;
use Polysource\BulkAsync\Job\DoctrineBulkJobStorage;
use Polysource\BulkAsync\Mercure\MercureBulkJobBroadcaster;
use Polysource\BulkAsync\Messenger\BulkJobHandler;
use Polysource\BulkAsync\Resource\BulkJobResource;
use Polysource\BulkAsync\Twig\BulkProgressExtension;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* ---------------------------------------------------------------
     * Storage — Doctrine-backed default, gated on Doctrine ORM.
     *
     * Apps without Doctrine ORM ship their own BulkJobStorageInterface
     * implementation (Redis, in-memory) under the same alias.
     * --------------------------------------------------------------- */
    if (interface_exists(EntityManagerInterface::class)) {
        $services->set(DoctrineBulkJobStorage::class)
            ->arg('$em', service(EntityManagerInterface::class));

        $services->alias(BulkJobStorageInterface::class, DoctrineBulkJobStorage::class)
            ->public();

        /* Read side: data source + browsable resource + cancel action. */
        $services->set(BulkJobDataSource::class)
            ->arg('$em', service(EntityManagerInterface::class));

        $services->set(BulkJobResource::class)
            ->arg('$dataSource', service(BulkJobDataSource::class))
            ->arg('$actions', tagged_iterator('polysource.bulk_async.action'));

        $services->set(CancelBulkJobAction::class)
            ->arg('$storage', service(BulkJobStorageInterface::class))
            ->tag('polysource.bulk_async.action');
    }

    /* ---------------------------------------------------------------
     * Async pipeline — dispatcher + Messenger handler.
     *
     * The handler is tagged via #[AsMessageHandler] on the class —
     * autoconfigure picks it up. We still register the service to
     * wire the optional event dispatcher (for ActionExecutedEvent +
     * BulkJobProgressEvent fan-out).
     * --------------------------------------------------------------- */
    $services->set(AsyncBulkActionDispatcher::class)
        ->arg('$storage', service(BulkJobStorageInterface::class))
        ->arg('$bus', service(MessageBusInterface::class))
        ->public();

    $services->set(BulkJobHandler::class)
        ->arg('$storage', service(BulkJobStorageInterface::class))
        ->arg('$resources', service(ResourceRegistry::class))
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid())
        ->tag('messenger.message_handler', ['handles' => Polysource\BulkAsync\Messenger\BulkJobMessage::class]);

    /* ---------------------------------------------------------------
     * HTTP surface — JSON progress endpoint (polling fallback).
     * --------------------------------------------------------------- */
    $services->set(ProgressController::class)
        ->arg('$storage', service(BulkJobStorageInterface::class))
        ->arg('$permission', service(PermissionInterface::class))
        // Optional: when no Symfony Security firewall is wired, the controller
        // falls back to the coarse VIEW gate alone. Production hosts always
        // have the firewall and therefore always get the ownership check.
        ->arg('$tokenStorage', service('security.token_storage')->nullOnInvalid())
        ->public()
        ->tag('controller.service_arguments');

    /* ---------------------------------------------------------------
     * Twig — progress card extension.
     * --------------------------------------------------------------- */
    $services->set(BulkProgressExtension::class)
        ->arg('$twig', service('twig'))
        ->arg('$progressUrlTemplate', '/admin/bulk-jobs/%%s/progress')
        ->tag('twig.extension');

    /* ---------------------------------------------------------------
     * Mercure broadcaster — optional; only registered when the
     * Mercure component is installed (cf. ADR-024 §8).
     * --------------------------------------------------------------- */
    if (class_exists(HubInterface::class)) {
        $services->set(MercureBulkJobBroadcaster::class)
            ->arg('$hub', service(HubInterface::class))
            ->arg('$logger', service('logger')->nullOnInvalid())
            ->tag('kernel.event_subscriber');
    }
};
