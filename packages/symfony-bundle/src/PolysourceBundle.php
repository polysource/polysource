<?php

declare(strict_types=1);

namespace Polysource\Bundle;

use Polysource\Bundle\DependencyInjection\PolysourceExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point.
 *
 * Auto-registered via Symfony Flex thanks to the `symfony-bundle` composer type.
 * Users may also register it manually in `config/bundles.php`.
 */
final class PolysourceBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
