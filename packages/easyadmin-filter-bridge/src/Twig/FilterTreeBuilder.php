<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;

/**
 * Builds the JSON tree the Stimulus controller
 * `polysource--filter-modal-layout` consumes to reorganise the EA-
 * rendered filter modal into tabs + groups.
 *
 * Output shape:
 *
 *   {
 *     "ungrouped": ["q", "name"],
 *     "groups": [
 *       {"label": "Status", "properties": ["isActive"]}
 *     ],
 *     "tabs": [
 *       {
 *         "label": "Visibility",
 *         "ungrouped": [],
 *         "groups": [
 *           {"label": "Active state", "properties": ["isVisible"]}
 *         ]
 *       }
 *     ]
 *   }
 *
 * Reading rules:
 * - `ungrouped` (top-level): filters with neither tab nor group.
 *   Render flat at the top, before any tabs.
 * - `groups` (top-level): filters with a group but no tab. Render
 *   as `<details>` accordions BELOW `ungrouped`.
 * - `tabs`: filters with a tab. Each tab carries its own
 *   `ungrouped` (no group within the tab) + `groups` (one
 *   `<details>` accordion each).
 *
 * Order is preserved: filters declared first in
 * `configureFilters()` appear first in their bucket, tabs are
 * encountered in declaration order, groups inside tabs likewise.
 */
final class FilterTreeBuilder
{
    /**
     * @return array{
     *     ungrouped: list<string>,
     *     groups: list<array{label: string, properties: list<string>}>,
     *     tabs: list<array{label: string, ungrouped: list<string>, groups: list<array{label: string, properties: list<string>}>}>
     * }
     */
    public function build(?FilterConfigDto $config): array
    {
        /** @var list<string> $ungrouped */
        $ungrouped = [];
        /** @var list<array{label: string, properties: list<string>}> $topGroups */
        $topGroups = [];
        /** @var list<array{label: string, ungrouped: list<string>, groups: list<array{label: string, properties: list<string>}>}> $tabs */
        $tabs = [];

        if (null === $config) {
            return ['ungrouped' => $ungrouped, 'groups' => $topGroups, 'tabs' => $tabs];
        }

        foreach ($config->all() as $entry) {
            if (!$entry instanceof FilterInterface) {
                continue;
            }

            $dto = $entry->getAsDto();
            $property = $dto->getProperty();
            if ('' === $property) {
                continue;
            }

            $rawTab = $dto->getCustomOption(BridgeOptions::TAB);
            $rawGroup = $dto->getCustomOption(BridgeOptions::GROUP);
            $tab = \is_string($rawTab) && '' !== $rawTab ? $rawTab : null;
            $group = \is_string($rawGroup) && '' !== $rawGroup ? $rawGroup : null;

            // Narrow $tab FIRST (nested rather than compound
            // conditions) so static analysis proves it non-null past
            // this block on every PHPStan build in the matrix.
            if (null === $tab) {
                if (null === $group) {
                    $ungrouped[] = $property;

                    continue;
                }

                $topGroups = $this->appendToGroup($topGroups, $group, $property);

                continue;
            }

            $tabIndex = $this->findTabIndex($tabs, $tab);
            if (-1 === $tabIndex) {
                /** @var array{label: string, ungrouped: list<string>, groups: list<array{label: string, properties: list<string>}>} $newTab */
                $newTab = ['label' => $tab, 'ungrouped' => [], 'groups' => []];
                $tabs[] = $newTab;
                $tabIndex = \count($tabs) - 1;
            }

            \assert($tabIndex >= 0 && $tabIndex < \count($tabs));
            $currentTab = $tabs[$tabIndex];
            if (null === $group) {
                $currentTab['ungrouped'][] = $property;
            } else {
                $currentTab['groups'] = $this->appendToGroup($currentTab['groups'], $group, $property);
            }
            $tabs[$tabIndex] = $currentTab;
        }

        return ['ungrouped' => $ungrouped, 'groups' => $topGroups, 'tabs' => $tabs];
    }

    /**
     * @param list<array{label: string, properties: list<string>}> $groups
     *
     * @return list<array{label: string, properties: list<string>}>
     */
    private function appendToGroup(array $groups, string $label, string $property): array
    {
        foreach ($groups as $i => $group) {
            if ($group['label'] === $label) {
                $groups[$i]['properties'][] = $property;

                return $groups;
            }
        }

        $groups[] = ['label' => $label, 'properties' => [$property]];

        return $groups;
    }

    /**
     * @param list<array{label: string, ungrouped: list<string>, groups: list<array{label: string, properties: list<string>}>}> $tabs
     */
    private function findTabIndex(array $tabs, string $label): int
    {
        foreach ($tabs as $i => $tab) {
            if ($tab['label'] === $label) {
                return $i;
            }
        }

        return -1;
    }
}
