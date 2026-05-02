<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Fixture;

use Polysource\Core\Permission\PermissionInterface;

/**
 * Test fixture that denies every permission attribute.
 */
final class AlwaysDenyPermission implements PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return false;
    }
}
