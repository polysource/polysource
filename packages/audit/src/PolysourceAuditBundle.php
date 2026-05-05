<?php

declare(strict_types=1);

namespace Polysource\Audit;

use Polysource\Audit\DependencyInjection\PolysourceAuditExtension;
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
 */
final class PolysourceAuditBundle extends Bundle
{
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
