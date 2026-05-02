<?php

declare(strict_types=1);

namespace Polysource\Bundle\Context;

/**
 * Holds the {@see AdminContext} for the duration of a single request.
 *
 * Cf. ADR-004 — the only mutable point in the system. Listeners that want
 * to enrich the context create a new {@see AdminContext} (via `with*()`) and
 * call {@see self::setContext()}. The context itself remains immutable.
 *
 * Scoped per request — Symfony's container resets this between requests
 * automatically when the service is registered with the `request` scope or
 * when sub-requests use a fresh container. For v0.1 we rely on the default
 * service scope plus the `kernel.reset` mechanism (see services.php).
 */
final class AdminContextProvider
{
    private ?AdminContext $context = null;

    public function getContext(): ?AdminContext
    {
        return $this->context;
    }

    public function setContext(AdminContext $context): void
    {
        $this->context = $context;
    }

    public function reset(): void
    {
        $this->context = null;
    }
}
