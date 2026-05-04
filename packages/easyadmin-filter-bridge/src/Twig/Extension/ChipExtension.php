<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_chip_value(property, rawValue)`
 * to the chip rendering templates.
 *
 * Pure delegation to {@see ChipValueFormatter}. Lives as a Twig
 * extension (rather than a service injected directly into the
 * template) because chip rendering happens inside an EA-managed
 * Twig include — the Twig namespace is the only seam where we
 * can expose a method without changing the template authoring
 * contract.
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
