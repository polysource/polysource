<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\RowDetail;

/**
 * Entity-FQCN → provider index over the tagged
 * `polysource.row_detail_provider` services.
 *
 * @since 1.1.0
 */
final class RowDetailRegistry
{
    /**
     * @var array<class-string, RowDetailProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param iterable<RowDetailProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $indexed = [];
        foreach ($providers as $provider) {
            // Later registrations override earlier ones — DI
            // override semantics, cf. the interface docblock.
            $indexed[$provider->getSupportedEntity()] = $provider;
        }
        $this->providers = $indexed;
    }

    public function providerFor(string $entityFqcn): ?RowDetailProviderInterface
    {
        return $this->providers[$entityFqcn] ?? null;
    }
}
