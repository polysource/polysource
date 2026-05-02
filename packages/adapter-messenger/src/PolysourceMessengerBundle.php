<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger;

use Polysource\Adapter\Messenger\DependencyInjection\PolysourceMessengerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for the Polysource Messenger adapter.
 */
final class PolysourceMessengerBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceMessengerExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
