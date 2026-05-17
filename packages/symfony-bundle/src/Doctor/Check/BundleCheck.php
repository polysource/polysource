<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Counts the Polysource bundles registered on the current kernel.
 * Empty = the user added composer requires but forgot to update
 * `config/bundles.php` — common pre-flex install bug.
 *
 * @since 0.9.0
 */
final class BundleCheck implements HealthCheckInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function getName(): string
    {
        return 'Polysource bundles';
    }

    public function run(): HealthCheckResult
    {
        $loaded = array_keys($this->kernel->getBundles());
        $polysource = array_filter(
            $loaded,
            static fn (string $name): bool => str_starts_with($name, 'Polysource'),
        );

        if ([] === $polysource) {
            return HealthCheckResult::fail(
                'No Polysource bundle is registered on this kernel. Add them to config/bundles.php.',
            );
        }

        return HealthCheckResult::pass(\sprintf(
            '%d registered (%s)',
            \count($polysource),
            implode(', ', $polysource),
        ));
    }
}
