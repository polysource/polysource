<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline;

use Polysource\Filter\Model\FilterCriterion;

/**
 * Second phase of the filter pipeline — produces a human-readable
 * label for an applied criterion.
 *
 * One formatter per filter `name`. Tagged services are registered as
 * `polysource.filter.formatter` and indexed by name by
 * `FormatterRegistry` (compile-time).
 *
 * Used by `FilterTagsExtension` to render chip labels above the
 * filtered list, and by audit/log extensions that want to capture
 * the user's intent in plain text.
 *
 * Implementations should respect the Symfony Translator if available,
 * so chips adapt to the active locale.
 */
interface FilterFormatterInterface
{
    public function supports(string $name): bool;

    /**
     * Returns the human label for the chip (e.g. `Date: 01/05 → 03/05`,
     * `Price: 50 € – 200 €`, `Status: Published`).
     *
     * **MUST return plain text** — no HTML, no script. The chips
     * template auto-escapes the result via `{{ chip.label }}`, BUT
     * `filter_tags()` itself is declared `is_safe => ['html']` (so
     * the surrounding rendered HTML isn't double-escaped). A
     * formatter returning `<script>...</script>` would *still* be
     * escaped at chip render time, but a host who templates the
     * output of a formatter directly elsewhere would expose XSS.
     * Treat the contract as "plain text only" — translate via the
     * Symfony Translator if locale-awareness is needed.
     */
    public function format(FilterCriterion $criterion): string;
}
