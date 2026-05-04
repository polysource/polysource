<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

/**
 * Canonical custom-option keys exposed by the bridge.
 *
 * Stored on EA's `FilterDto::customOptions` / `FieldDto::customOptions`
 * — the official EA channel for non-form metadata
 * (cf. `LanguageFilter::OPTION_USE_ALPHA3_CODES` for the same pattern
 * inside EA itself).
 *
 * Namespacing: every key is prefixed with `polysource.` so the
 * bridge's options never clash with EA's own (`tabs`, `tabId`,
 * `OPTION_*` from FileField/UrlField/etc.) or with other host
 * extensions.
 *
 * Hosts read these on a FilterDto/FieldDto via:
 *
 *     $dto->getCustomOption(BridgeOptions::CHIP_FORMATTER);
 *
 * Or write via the fluent {@see Polysource} facade:
 *
 *     Polysource::filter($filter)->chipFormatter(fn ($v) => …);
 *
 * which delegates to `$dto->setCustomOption(BridgeOptions::CHIP_FORMATTER, $cb)`.
 */
final class BridgeOptions
{
    /** Tab the filter/field belongs to. Subpanel mode renders these as Bootstrap nav-tabs. */
    public const TAB = 'polysource.tab';

    /** Group within a tab (or top-level). Renders as `<details>` accordion. */
    public const GROUP = 'polysource.group';

    /**
     * Custom chip-rendering callable: `fn (mixed $rawValue): string`.
     * Invoked by ChipValueFormatter at the highest priority in the
     * 5-stage resolution chain.
     */
    public const CHIP_FORMATTER = 'polysource.chip_formatter';

    /**
     * Custom request → criterion mapper: `fn (array $rawValues): mixed`.
     * Reserved for hosts implementing exotic URL shapes. v0.1 unused
     * by the bridge core; documented for v0.2 forward-compat.
     */
    public const MAPPER = 'polysource.mapper';

    /**
     * Custom criterion → human label callable. Distinct from
     * CHIP_FORMATTER: the chip formatter operates on a single raw
     * value; the formatter operates on the whole criterion (e.g.
     * "Created at: 01/05 → 03/05"). v0.2 forward-compat.
     */
    public const FORMATTER = 'polysource.formatter';

    /**
     * Custom FormType FQCN to override the renderer. EA already
     * exposes `setFormType()` for this — this option exists for
     * symmetry with the polysource/filter primitive's pipeline.
     */
    public const RENDERER = 'polysource.renderer';

    /**
     * Internal flag set by `Polysource::tab()` / `Polysource::group()`
     * markers so the propagation processor can identify them.
     */
    public const IS_MARKER = 'polysource.is_marker';

    private function __construct()
    {
    }
}
