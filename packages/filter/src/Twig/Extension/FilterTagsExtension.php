<?php

declare(strict_types=1);

namespace Polysource\Filter\Twig\Extension;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterDefinition;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `filter_tags(collection, definitions)` —
 * renders the chips bar for an active `FilterCollection`.
 *
 * Each chip displays the per-criterion label produced by the
 * matching `FilterFormatterInterface` (resolved via
 * `FormatterRegistry`). The template is
 * `@PolysourceFilter/tags/chips.html.twig` by default; hosts override
 * by passing a custom template path as the third argument.
 *
 * The extension delegates the actual HTML rendering to Twig (via
 * `Environment::render()`) so:
 * - hosts can override the template by aliasing `@PolysourceFilter`
 *   to a custom path,
 * - any nested Twig (translations, Stimulus data attrs) participates
 *   in the global rendering pipeline (e.g. CSP nonce injection).
 */
final class FilterTagsExtension extends AbstractExtension
{
    public const DEFAULT_TEMPLATE = '@PolysourceFilter/tags/chips.html.twig';

    public function __construct(
        private readonly FormatterRegistry $formatters,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'filter_tags',
                $this->renderTags(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * @param list<FilterDefinition> $definitions Available filter definitions
     */
    public function renderTags(
        FilterCollection $collection,
        array $definitions,
        string $template = self::DEFAULT_TEMPLATE,
    ): string {
        // Index definitions by property for O(1) lookup so chips can
        // pick up labels and groups from their declaration.
        $definitionByProperty = [];
        foreach ($definitions as $definition) {
            $definitionByProperty[$definition->property] = $definition;
        }

        $chips = [];
        foreach ($collection as $criterion) {
            $definition = $definitionByProperty[$criterion->property] ?? null;
            $name = $definition?->name;
            if (null === $name || !$this->formatters->has($name)) {
                // Skip criteria for which we have no formatter — host
                // must register one if they want them rendered. This
                // prevents the chips bar from crashing on schema drift.
                continue;
            }

            $formatter = $this->formatters->forName($name);
            $chips[] = [
                'criterion' => $criterion,
                'definition' => $definition,
                'label' => $formatter->format($criterion),
            ];
        }

        return $this->twig->render($template, [
            'collection' => $collection,
            'chips' => $chips,
            'collection_id' => $collection->id,
        ]);
    }
}
