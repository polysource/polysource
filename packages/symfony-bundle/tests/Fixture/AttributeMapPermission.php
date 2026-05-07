<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Fixture;

use Polysource\Core\Permission\PermissionInterface;

/**
 * Test fixture allowing per-attribute grant/deny via an injected map.
 *
 * Built for the field-permission and action-isDisplayed coverage tests
 * where the kernel must grant resource-level access but deny specific
 * field-level or action-level attributes — a single boolean stub
 * (AlwaysGrant / AlwaysDeny) can't express that.
 */
final class AttributeMapPermission implements PermissionInterface
{
    /** @var array<string, bool> */
    private array $map;

    /**
     * @param array<string, bool> $map           attribute → grant decision
     * @param bool                $defaultGrant  decision when an attribute is not in the map
     */
    public function __construct(
        array $map = [],
        private readonly bool $defaultGrant = true,
    ) {
        $this->map = $map;
    }

    /** @param array<string, bool> $map */
    public function setMap(array $map): void
    {
        $this->map = $map;
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->map[$attribute] ?? $this->defaultGrant;
    }
}
