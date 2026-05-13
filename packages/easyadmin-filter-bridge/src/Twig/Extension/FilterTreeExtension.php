<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing
 * `polysource_filter_tree(filterConfigDto): array` — returns the
 * structured tree (ungrouped / groups / tabs) the bridge's
 * `crud/filters.html.twig` override iterates to render server-side
 * `<details name="polysource-tab">` tabs and `<details>` group
 * accordions around the EA filter form fields.
 *
 * Earlier versions (v0.1.x) returned a JSON-encoded string consumed
 * by the `polysource--filter-modal-layout` Stimulus controller, which
 * reorganised the AJAX-loaded form client-side. v0.2.0 moved that
 * work server-side (cf. ADR-027 — every interactive feature MUST
 * have a server-side baseline), so the function now returns the
 * native array shape directly.
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
            new TwigFunction('polysource_filter_tree', $this->buildTree(...)),
        ];
    }

    /**
     * @return array{
     *     ungrouped: list<string>,
     *     groups: list<array{label: string, properties: list<string>}>,
     *     tabs: list<array{label: string, ungrouped: list<string>, groups: list<array{label: string, properties: list<string>}>}>
     * }
     */
    public function buildTree(?FilterConfigDto $config): array
    {
        return $this->builder->build($config);
    }
}
