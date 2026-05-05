<?php

declare(strict_types=1);

use Polysource\Audit\EventListener\ActionAuditSubscriber;
use Polysource\Audit\Logger\AggregateAuditLogger;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Logger\NullAuditLogger;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\SymfonySecurityAuditActor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* ---------------------------------------------------------------
     * Logger fan-out
     *
     * `polysource.audit_logger` is the host-facing tag — every
     * concrete sink (Datadog, syslog, Doctrine, …) is registered
     * with this tag and the aggregator iterates them.
     *
     * The `AuditLoggerInterface` autowiring alias points at the
     * aggregator so subscribers / hosts inject "the audit logger"
     * without knowing it's actually a fan-out.
     * --------------------------------------------------------------- */
    $services->set(NullAuditLogger::class)
        // Ship NullAuditLogger as the safe default. Hosts that want
        // real logging override the polysource.audit_logger tag with
        // their own service (DoctrineAuditLogger lands in batch D).
        ->tag('polysource.audit_logger');

    $services->set(AggregateAuditLogger::class)
        ->arg('$loggers', tagged_iterator('polysource.audit_logger'))
        ->arg('$errorLogger', service('logger')->nullOnInvalid());

    $services->alias(AuditLoggerInterface::class, AggregateAuditLogger::class)
        ->public();

    /* ---------------------------------------------------------------
     * Actor resolver
     *
     * Default: SymfonySecurityAuditActor (pulls from Security::getUser).
     * Hosts swap in their own AuditActorInterface impl by aliasing
     * AuditActorInterface to it.
     * --------------------------------------------------------------- */
    $services->set(SymfonySecurityAuditActor::class)
        // Security service is optional — apps without symfony/security-bundle
        // see a null and fall back to the anonymous sentinel for every entry.
        ->arg('$security', service(Security::class)->nullOnInvalid());

    $services->alias(AuditActorInterface::class, SymfonySecurityAuditActor::class);

    /* ---------------------------------------------------------------
     * Action lifecycle subscriber
     * --------------------------------------------------------------- */
    $services->set(ActionAuditSubscriber::class)
        ->arg('$logger', service(AuditLoggerInterface::class))
        ->arg('$actor', service(AuditActorInterface::class));
};
