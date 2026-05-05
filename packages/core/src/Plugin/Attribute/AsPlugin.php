<?php

declare(strict_types=1);

namespace Polysource\Core\Plugin\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * Declares the host class as a Polysource plugin.
 *
 * Combined with {@see \Polysource\Core\Plugin\HasPluginMetadata}, the
 * attribute lets a Bundle expose `name` + `version` to
 * `PluginRegistry` without writing the three
 * {@see \Polysource\Core\Plugin\AdminPluginInterface} method bodies
 * by hand.
 *
 * Usage:
 *
 * ```php
 * #[AsPlugin(name: 'polysource/adapter-messenger', version: '0.1.0')]
 * final class PolysourceAdapterMessengerBundle extends Bundle
 * {
 *     use HasPluginMetadata;
 * }
 * ```
 *
 * Per ADR-018 §3, the attribute is part of the public API and
 * follows semver from v1.0.0 — adding required parameters would be a
 * breaking change.
 *
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsPlugin
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('AsPlugin name cannot be empty.');
        }

        if ('' === $version) {
            throw new InvalidArgumentException('AsPlugin version cannot be empty.');
        }
    }
}
