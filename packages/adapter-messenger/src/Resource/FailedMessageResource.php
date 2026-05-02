<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Resource;

use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Resource\AbstractResource;

/**
 * Polysource resource exposing the Messenger failed transport.
 *
 * Cf. ADR-005 — `#[AsResource]` auto-tags this class with
 * `polysource.resource`. The slug is overridable via the
 * `polysource_messenger.resource_slug` configuration node so users with
 * an existing route prefix can avoid collisions.
 */
#[AsResource]
final class FailedMessageResource extends AbstractResource
{
    public function __construct(
        MessengerFailedDataSource $dataSource,
        private readonly string $slug = 'failed-messages',
    ) {
        parent::__construct($dataSource);
    }

    public function getName(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return 'Failed messages';
    }

    public function getIdentifierProperty(): string
    {
        // The DataRecord identifier is the Messenger transport id (string).
        // We expose `message_class` as the user-facing label in the index.
        return 'message_class';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FAILED_MESSAGE';
    }

    public function configureFields(string $page): iterable
    {
        // Concrete field types live in the host application's preview /
        // demo for now; v0.1 of `polysource/core` ships only the abstract
        // FieldInterface + FieldTrait. Phase 4 deliberately keeps this
        // resource minimal — Phase 9 (user docs) covers the field-type
        // story properly.
        return [];
    }
}
