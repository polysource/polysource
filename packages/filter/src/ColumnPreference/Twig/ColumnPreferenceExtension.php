<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Twig;

use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing:
 *
 * - `polysource_column_hidden(resourceName, propertyName): bool` —
 *   true iff the current user has hidden the given column for the
 *   given resource. Templates wrap `<th>` and `<td>` rendering in
 *   `{% if not polysource_column_hidden(...) %}` to gate visibility.
 *
 * - `polysource_hidden_columns(resourceName): list<string>` —
 *   returns the full list of hidden columns for the current user
 *   on the given resource. Used by the visibility-toggle dropdown
 *   to pre-check the checkboxes for hidden columns.
 *
 * Both functions return safe defaults for anonymous users (false
 * and []), so templates can call them unconditionally.
 *
 * @since 0.3.0
 */
final class ColumnPreferenceExtension extends AbstractExtension
{
    public function __construct(private readonly ?ColumnPreferenceService $service = null)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_column_hidden', $this->isHidden(...)),
            new TwigFunction('polysource_hidden_columns', $this->hiddenColumns(...)),
        ];
    }

    public function isHidden(string $resourceName, string $propertyName): bool
    {
        if (null === $this->service) {
            return false;
        }

        return \in_array($propertyName, $this->service->hiddenColumns($resourceName), true);
    }

    /**
     * @return list<string>
     */
    public function hiddenColumns(string $resourceName): array
    {
        if (null === $this->service) {
            return [];
        }

        return $this->service->hiddenColumns($resourceName);
    }
}
