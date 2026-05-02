<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Resource;

use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Action\ActionInterface;
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
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        MessengerFailedDataSource $dataSource,
        private readonly string $slug = 'failed-messages',
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
        // v0.1 of polysource/core ships only the abstract FieldInterface
        // + FieldTrait; concrete field types live in the host
        // application until the core ships built-in TextField /
        // BooleanField / DateTimeField / CodeField / IdField. Once
        // those land, this method should yield the appropriate fields
        // for the message_class / failed_at / exception_class /
        // payload columns.
        return [];
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }
}
