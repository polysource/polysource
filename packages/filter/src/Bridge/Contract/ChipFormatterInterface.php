<?php

declare(strict_types=1);

namespace Polysource\Filter\Bridge\Contract;

/**
 * Contract for formatting a raw filter value into a chip label —
 * the small badge shown above an admin list to indicate which
 * filters are currently applied.
 *
 * This contract lives in `polysource/filter` (the tronc commun) so
 * every bridge to a host admin framework — `polysource/easyadmin-filter-bridge`
 * today, future Sonata or API Platform bridges tomorrow — accepts
 * the same interface. A `MyChipFormatter implements ChipFormatterInterface`
 * service can move from one bridge to another without code change.
 *
 * Cf. ADR-016 — Bridge contracts shared with `polysource/filter`.
 *
 * **Bridge namespace vs Pipeline namespace** — the `Pipeline\*Interface`
 * family operates on a `FilterCriterion` (the immutable model), and
 * targets hosts that consume `polysource/filter` natively without an
 * admin framework. `Bridge\Contract\*Interface` operates on a raw
 * URL-state value — the shape that admin bridges like EasyAdmin or
 * Sonata expose to chip-rendering code. The two namespaces don't
 * meet at runtime; they cover orthogonal use cases.
 *
 * **Plain text contract** — implementations MUST return plain text.
 * Chip rendering templates auto-escape the result (so an
 * accidental `<script>...</script>` won't render as HTML), but a
 * host that pipes a formatter's output through another channel
 * (logs, audit trails, JSON exports) would expose XSS / injection
 * if the formatter returned HTML. Translate via
 * `Symfony\Contracts\Translation\TranslatorInterface` for
 * locale-awareness if needed.
 */
interface ChipFormatterInterface
{
    /**
     * Returns the human-readable chip label for a raw filter value.
     *
     * @param mixed $rawValue the raw value as it appears in the URL
     *                        filter slice (typically string for scalar
     *                        filters, array for `BooleanFilter` /
     *                        `EntityFilter` / `BetweenDateFilter`,
     *                        `Stringable`/scalar otherwise)
     */
    public function format(mixed $rawValue): string;
}
