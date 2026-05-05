<?php

declare(strict_types=1);

namespace Polysource\Audit;

use Polysource\Audit\DependencyInjection\PolysourceAuditExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/audit`.
 *
 * Hosts register this in `config/bundles.php`:
 *
 *   Polysource\Audit\PolysourceAuditBundle::class => ['all' => true],
 *
 * The DI extension lives in {@see PolysourceAuditExtension} — gating
 * the Doctrine-dependent services on `interface_exists(EntityManagerInterface)`
 * per ADR-019 §4 / ADR-020 §6.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — the bundle
 * surfaces in `polysource:plugins:list` alongside core / filter /
 * symfony-bundle / adapter-messenger.
 */
#[AsPlugin(name: 'polysource/audit', version: '0.1.0-alpha.1')]
final class PolysourceAuditBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceAuditExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
