<?php

declare(strict_types=1);

namespace Polysource\Core\RowDetail;

/**
 * Value object describing what to render inside an expanded row —
 * a Twig template plus its context. Returned by the bridge's
 * `RowDetailProviderInterface::getRowDetail()` and the bundle's
 * `HasRowDetailsInterface::getRowDetail()`.
 *
 * Lives in core (a template *name* is an opaque string here — no
 * Twig dependency) so both downstream packages share one shape,
 * per the ADR-018 ≥2-consumers budget rule.
 *
 * The template always receives the row's entity as `entity` on top
 * of the given context (a `entity` key in `$context` wins, for
 * providers that need to alias it).
 *
 * Deliberately template-only for now: "a few extra fields", "a
 * custom panel" and "a table of related records" are all Twig
 * templates from the renderer's point of view. Richer content types
 * (a nested Polysource listing with its own filters/pagination)
 * are a planned follow-up and will extend this VO, not replace it.
 *
 * @since 1.1.0
 */
final class RowDetail
{
    /**
     * @param array<string, mixed> $context
     */
    private function __construct(
        public readonly string $template,
        public readonly array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context extra variables for the template
     */
    public static function template(string $template, array $context = []): self
    {
        return new self($template, $context);
    }
}
