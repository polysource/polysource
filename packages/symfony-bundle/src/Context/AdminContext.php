<?php

declare(strict_types=1);

namespace Polysource\Bundle\Context;

use Polysource\Core\Query\DataQuery;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Per-request admin state injected into Polysource controllers.
 *
 * Cf. ADR-004 — `final readonly` to guarantee invariance during the request
 * lifecycle. Listeners that need to enrich the context call
 * {@see AdminContextProvider::setContext()} with a derived instance.
 *
 * Stop-the-line: keep this to ≤ 10 properties. Beyond that, decompose into
 * sub-contexts.
 */
final class AdminContext
{
    public function __construct(
        public readonly Request $request,
        public readonly ResourceInterface $resource,
        public readonly string $action,
        public readonly ?string $recordId,
        public readonly string $locale,
        public readonly ?UserInterface $user,
        public readonly DataQuery $query,
    ) {
    }

    public function withQuery(DataQuery $query): self
    {
        return new self(
            $this->request,
            $this->resource,
            $this->action,
            $this->recordId,
            $this->locale,
            $this->user,
            $query,
        );
    }
}
