<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge;

use Polysource\EasyAdminFilterBridge\DependencyInjection\PolysourceEasyAdminFilterBridgeExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for the EasyAdmin filter bridge.
 *
 * Auto-registered via Symfony Flex thanks to the `symfony-bundle` composer
 * type. Users may also register it manually in `config/bundles.php`.
 *
 * The bundle injects `FilterConfiguratorInterface` services that EasyAdmin's
 * `FilterFactory` consumes to mutate filter DTOs (formType, formTypeOptions)
 * after the built-in filter classes have produced them. No EasyAdmin code
 * is modified — see ADR-012.
 */
final class PolysourceEasyAdminFilterBridgeBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceEasyAdminFilterBridgeExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
