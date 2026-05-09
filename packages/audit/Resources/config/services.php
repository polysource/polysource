<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Audit\Action\ExportAuditCsvAction;
use Polysource\Audit\Command\PurgeAuditCommand;
use Polysource\Audit\DataSource\AuditLogDataSource;
use Polysource\Audit\EventListener\ActionAuditSubscriber;
use Polysource\Audit\EventListener\EasyAdminAuditSubscriber;
use Polysource\Audit\Export\AuditCsvExporter;
use Polysource\Audit\Logger\AggregateAuditLogger;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Logger\DoctrineAuditLogger;
use Polysource\Audit\Logger\NullAuditLogger;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\SymfonySecurityAuditActor;
use Polysource\Audit\Resource\AuditLogResource;
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
    /* Doctrine-backed default sink — only registered when Doctrine ORM is
       available. Apps without Doctrine fall back to NullAuditLogger below.
       The check uses interface_exists (NOT class_exists) — EntityManagerInterface
       is, well, an interface; class_exists returns false for interfaces. */
    if (interface_exists(EntityManagerInterface::class)) {
        $services->set(DoctrineAuditLogger::class)
            ->arg('$em', service(EntityManagerInterface::class))
            ->tag('polysource.audit_logger');

        /* The browsable AuditLogResource (cf. ADR-020 §7) — read side
           of the audit pipeline. Auto-tagged via #[AsResource] (ADR-005)
           so PolysourceBundle's resource registry picks it up. */
        $services->set(AuditLogDataSource::class)
            ->arg('$em', service(EntityManagerInterface::class));

        $services->set(AuditLogResource::class)
            ->arg('$dataSource', service(AuditLogDataSource::class))
            ->arg('$actions', tagged_iterator('polysource.audit.action'));

        /* CSV export action — GDPR Art. 30 register extraction.
           Default export directory is `%kernel.cache_dir%/polysource-audit-export`;
           hosts override via a parameter or by re-defining the service. */
        $services->set(AuditCsvExporter::class);

        $services->set(ExportAuditCsvAction::class)
            ->arg('$em', service(EntityManagerInterface::class))
            ->arg('$exporter', service(AuditCsvExporter::class))
            ->arg('$exportDirectory', '%kernel.cache_dir%/polysource-audit-export')
            ->tag('polysource.audit.action');

        /* Retention command — `bin/console polysource:audit:purge`.
           Auto-tagged via #[AsCommand]; we still need to register the
           service so DI can wire its EntityManager dependency. */
        $services->set(PurgeAuditCommand::class)
            ->arg('$em', service(EntityManagerInterface::class));
    } else {
        $services->set(NullAuditLogger::class)
            ->tag('polysource.audit_logger');
    }

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

    /* ---------------------------------------------------------------
     * EasyAdmin lifecycle bridge — audits Edit / New / Delete on EA
     * CRUD pages (Doctrine entities). Gated on EA being installed so
     * audit-only hosts (no EA) don't pull in EA's autoload graph.
     *
     * Without this, EA Doctrine edits bypass the Polysource action
     * pipeline and silently escape the audit trail — the only entries
     * recorded would be Polysource-native actions (failed-message
     * retry, bulk-job cancel, workflow transitions).
     * --------------------------------------------------------------- */
    if (class_exists(EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent::class)) {
        $services->set(EasyAdminAuditSubscriber::class)
            ->arg('$logger', service(AuditLoggerInterface::class))
            ->arg('$actor', service(AuditActorInterface::class))
            ->arg('$requestStack', service('request_stack'));
    }
};
