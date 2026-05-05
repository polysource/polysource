<?php

declare(strict_types=1);

namespace Polysource\Audit\Resource;

use Polysource\Audit\DataSource\AuditLogDataSource;
use Polysource\Audit\Filter\AuditLogFilter;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Polysource resource exposing the `polysource_audit_log` table —
 * the audit log is itself browsable in the admin (cf. ADR-020 §7).
 *
 * Auto-tagged via {@see AsResource} (ADR-005). The slug
 * `audit-log` is intentional — short, kebab-case, and unlikely to
 * collide with host resources.
 *
 * Permission: `POLYSOURCE_AUDIT_VIEW`. Hosts grant this to operators
 * who legitimately need to read the audit trail (compliance officer,
 * SRE on-call, …) and withhold from regular admin users.
 *
 * Not `final` so host applications can subclass to localise labels
 * or add custom inline actions without forking the package.
 *
 * Read-only by design. The audit log carries no destructive inline
 * actions — entries are immutable once persisted. The only bulk
 * action shipped is `ExportAuditCsvAction` (GDPR Art. 30 register
 * export) — wired in batch F.
 */
#[AsResource]
class AuditLogResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        AuditLogDataSource $dataSource,
        private readonly string $slug = 'audit-log',
        private readonly iterable $actions = [],
    ) {
        parent::__construct($dataSource);
    }

    public function getName(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return 'Audit log';
    }

    public function getIdentifierProperty(): string
    {
        return 'id';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_AUDIT_VIEW';
    }

    public function configureFields(string $page): iterable
    {
        // v0.1 ships only the abstract FieldInterface in core
        // (cf. FailedMessageResource). The detail page renders raw
        // DataRecord properties; concrete TextField / DateTimeField /
        // BadgeField subclasses ship in a later release.
        return [];
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }

    public function configureFilters(): iterable
    {
        yield AuditLogFilter::occurredAt();
        yield AuditLogFilter::actorId();
        yield AuditLogFilter::resourceName();
        yield AuditLogFilter::actionName();
        yield AuditLogFilter::outcome();
    }
}
