<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Resource;

use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Filter\FailedMessageFilter;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Polysource resource exposing the Messenger failed transport.
 *
 * Cf. ADR-005 — `#[AsResource]` auto-tags this class with
 * `polysource.resource`. The slug is overridable via the
 * `polysource_messenger.resource_slug` configuration node so users
 * with an existing route prefix can avoid collisions.
 *
 * Not `final` so host applications can subclass to override
 * `configureFields()` — e.g. to yield the concrete field types
 * shipped by `polysource/core` (TextField, DateTimeField, …) for the
 * columns they care about. Subclasses MUST keep the constructor
 * signature compatible with the DI extension's argument wiring.
 */
#[AsResource]
class FailedMessageResource extends AbstractResource
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
        // Intentionally empty: the theme derives columns from the
        // record when no fields are configured, which keeps this
        // resource agnostic of the host's envelope shape. Core ships
        // concrete field types (TextField / DateTimeField / CodeField /
        // IdField / BooleanField) — yielding a curated set here for
        // message_class / failed_at / exception_class / payload is an
        // open improvement; subclass to opt in today.
        return [];
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }

    /**
     * Default filter set covering the 3 properties operators most often
     * triage on: which message classes are failing, which exception
     * classes triggered, and the time window. Subclasses are free to
     * extend or replace this list.
     *
     * The filters are translated to client-side post-mapping filtering
     * inside {@see MessengerFailedDataSource::search()} — the underlying
     * Messenger receivers don't expose a native filtering primitive.
     */
    public function configureFilters(): iterable
    {
        yield FailedMessageFilter::messageClass();
        yield FailedMessageFilter::exceptionClass();
        yield FailedMessageFilter::failedAt();
    }
}
