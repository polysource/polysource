<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing the bridge's chip value formatter helper.
 *
 * Lives as a Twig extension (rather than a service injected directly
 * into the template) because chip rendering happens inside an
 * EA-managed Twig include — the Twig namespace is the only seam
 * where we can expose a method without changing the template
 * authoring contract.
 *
 * Historical note (v0.1.2 — v0.1.3): this extension also registered
 * `polysource_saved_views_available()` and a stub for
 * `saved_views_dropdown()`. Both were workarounds for an
 * architectural defect where `saved_views_dropdown` was owned by
 * `polysource/symfony-bundle` despite being relevant to bridge-alone
 * installs. v0.1.4 moves ownership of `saved_views_dropdown` to
 * `polysource/filter::SavedViewExtension`, which the bridge pulls
 * transitively — so the function is always reachable and no
 * stub-or-gate is required. The historical helpers have been removed.
 */
final class ChipExtension extends AbstractExtension
{
    public function __construct(private readonly ChipValueFormatter $formatter)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_chip_value', $this->formatter->format(...)),
        ];
    }
}
