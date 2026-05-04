<?php

declare(strict_types=1);

namespace Polysource\Bundle\View;

/**
 * Controller return value processed by the Polysource response listener.
 *
 * Letting controllers return a small DTO keeps them framework-agnostic and
 * opens the door to multiple rendering strategies (Twig in Phase 3, JSON for
 * smoke tests in Phase 2). Cf. dev-plan Phase 2 §kernel.view listener.
 */
final class PolysourceView
{
    /**
     * @param array<string, mixed> $variables passed verbatim to the renderer
     */
    public function __construct(
        public readonly string $template,
        public readonly array $variables = [],
        public readonly int $statusCode = 200,
    ) {
    }
}
