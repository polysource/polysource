<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing
 * `polysource_filter_tree(filterConfigDto): string` — returns a
 * JSON string the bridge's `crud/index.html.twig` injects into a
 * `data-polysource--filter-modal-layout-tree-value` attribute on
 * the `#modal-filters` element.
 *
 * The Stimulus controller `polysource--filter-modal-layout` reads
 * this JSON and reorganises the EA-rendered filter modal into
 * tabs + group accordions matching the host's customOptions.
 */
final class FilterTreeExtension extends AbstractExtension
{
    public function __construct(private readonly FilterTreeBuilder $builder)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_filter_tree', $this->renderTree(...)),
        ];
    }

    public function renderTree(?FilterConfigDto $config): string
    {
        $tree = $this->builder->build($config);

        // JSON_THROW_ON_ERROR keeps surprises out of the chain — we
        // rather error in dev than silently inject malformed JSON
        // that'd break the Stimulus controller at runtime.
        return json_encode($tree, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
