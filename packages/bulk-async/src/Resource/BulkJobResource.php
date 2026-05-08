<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Resource;

use Polysource\BulkAsync\DataSource\BulkJobDataSource;
use Polysource\BulkAsync\Filter\BulkJobFilter;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Polysource resource exposing the `polysource_bulk_jobs` table —
 * the async job dashboard (cf. ADR-024 §7).
 *
 * Auto-tagged via {@see AsResource} (ADR-005). Slug `bulk-jobs` is
 * intentional: short, kebab-case, unlikely to collide with host
 * resources.
 *
 * Permission: `POLYSOURCE_BULK_JOB_VIEW`. Hosts grant this to
 * operators monitoring background work (SRE on-call, ops dashboard
 * users); the cancel inline action is gated separately on
 * `POLYSOURCE_BULK_JOB_CANCEL`.
 *
 * Not `final` so host applications can subclass to localise labels
 * or add custom inline actions without forking the package.
 */
#[AsResource]
class BulkJobResource extends AbstractResource
{
    public const PERMISSION_VIEW = 'POLYSOURCE_BULK_JOB_VIEW';

    /**
     * Admin-style override letting an operator inspect any user's
     * bulk-job progress, not only their own. Without this attribute,
     * {@see \Polysource\BulkAsync\Controller\ProgressController}
     * enforces actor-only access (the requester must own the job).
     * Hosts grant this to SREs and platform admins via their voter.
     */
    public const PERMISSION_VIEW_ANY = 'POLYSOURCE_BULK_JOB_VIEW_ANY';

    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        BulkJobDataSource $dataSource,
        private readonly string $slug = 'bulk-jobs',
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
        return 'Bulk jobs';
    }

    public function getIdentifierProperty(): string
    {
        return 'id';
    }

    public function getPermission(): string
    {
        return self::PERMISSION_VIEW;
    }

    public function configureFields(string $page): iterable
    {
        unset($page);

        return [];
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }

    public function configureFilters(): iterable
    {
        yield BulkJobFilter::actorId();
        yield BulkJobFilter::status();
        yield BulkJobFilter::createdAt();
        yield BulkJobFilter::resourceName();
    }
}
