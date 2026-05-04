<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use LogicException;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Processes `Polysource::tab()` / `Polysource::group()` markers
 * in the FilterConfigDto:
 *
 *   1. Walks filters in declaration order.
 *   2. When a marker is encountered, updates the "current tab"
 *      / "current group" state. Tab markers RESET the current
 *      group (a new tab implicitly starts a fresh group context).
 *   3. Non-marker filters inherit the current tab/group **only if
 *      they don't already have one explicitly set** — per-filter
 *      `Polysource::filter($f)->tab(...)` always wins.
 *   4. Rebuilds a fresh FilterConfigDto without the markers, then
 *      assigns it via `Crud::setFiltersConfig()`.
 *
 * Wired on `KernelEvents::CONTROLLER` (same hook as
 * {@see FilterFormThemeRegistrationSubscriber}) because:
 *   - `BeforeCrudActionEvent` is NOT dispatched by EA's
 *     `renderFilters` AJAX action, so a subscriber on it would
 *     miss the modal-form load — markers would leak through to
 *     the form rendering.
 *   - `kernel.controller` fires for every controller invocation
 *     including AJAX endpoints. The processor is idempotent — a
 *     second pass sees no markers (already removed) and no-ops.
 *
 * Marker propagation makes the host's `configureFilters()` read
 * sequentially, like EA's own `FormField::addTab()` / `addFieldset()`
 * pattern in `configureFields()`. Per-filter explicit declarations
 * still work and override inheritance.
 */
final class FilterMarkerProcessor implements EventSubscriberInterface
{
    public function __construct(
        private readonly AdminContextProviderInterface $adminContextProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            return;
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            return;
        }

        $original = $crud->getFiltersConfig();
        $entries = $original->all();
        if ([] === $entries) {
            return;
        }

        // Detect marker presence cheaply — if no markers, exit
        // without rebuilding (avoid CPU cost on every request for
        // non-marker-mode controllers).
        $hasMarker = false;
        foreach ($entries as $entry) {
            if ($this->isMarker($entry)) {
                $hasMarker = true;
                break;
            }
        }
        if (!$hasMarker) {
            return;
        }

        $rebuilt = new FilterConfigDto();
        $currentTab = null;
        $currentGroup = null;
        $hasAnyTabMarker = false;
        $orphanedFilters = [];

        foreach ($entries as $entry) {
            if ($this->isMarker($entry)) {
                /** @var FilterInterface $entry */
                $dto = $entry->getAsDto();

                $tab = $dto->getCustomOption(BridgeOptions::TAB);
                if (\is_string($tab)) {
                    $hasAnyTabMarker = true;
                    $currentTab = $tab;
                    // Tab marker resets the group context — a new
                    // tab implicitly starts fresh. Hosts that want
                    // a group on the new tab declare another marker
                    // immediately after.
                    $currentGroup = null;

                    continue;
                }

                $group = $dto->getCustomOption(BridgeOptions::GROUP);
                if (\is_string($group)) {
                    $currentGroup = $group;

                    continue;
                }

                // Marker without TAB or GROUP — corrupted, skip.
                continue;
            }

            // Real filter — inherit tab/group from current state
            // if not already set explicitly via `Polysource::filter()->tab()`.
            if ($entry instanceof FilterInterface) {
                $dto = $entry->getAsDto();

                if (null !== $currentTab && null === $dto->getCustomOption(BridgeOptions::TAB)) {
                    $dto->setCustomOption(BridgeOptions::TAB, $currentTab);
                }

                if (null !== $currentGroup && null === $dto->getCustomOption(BridgeOptions::GROUP)) {
                    $dto->setCustomOption(BridgeOptions::GROUP, $currentGroup);
                }

                // Track filters that ended up with no tab — used by
                // the strict-mode check after the loop.
                $resolvedTab = $dto->getCustomOption(BridgeOptions::TAB);
                if (!\is_string($resolvedTab) || '' === $resolvedTab) {
                    $orphanedFilters[] = $dto->getProperty();
                }
            }

            // FilterConfigDto::addFilter() accepts FilterInterface|string;
            // FilterConfigDto::all() loosely returns mixed entries
            // (legacy support for the deprecated string form). Skip
            // anything else as a defensive measure.
            if ($entry instanceof FilterInterface || \is_string($entry)) {
                $rebuilt->addFilter($entry);
            }
        }

        // Strict-mode check (mirrors EA's `FormField::addTab()` rule):
        // if ANY tab marker was used, EVERY filter must end up under
        // a tab. Same wording / mental model as EA's "When using form
        // tabs, all fields must be rendered inside a tab".
        if ($hasAnyTabMarker && [] !== $orphanedFilters) {
            throw new LogicException(\sprintf('When using filter tabs, all filters must belong to a tab. However, your filter(s) "%s" do not belong to any tab. Move them under a tab marker (Polysource::tab(\'X\')) BEFORE the filter declarations, or remove all tab markers from configureFilters() to fall back to a flat layout.', implode('", "', $orphanedFilters)));
        }

        $crud->setFiltersConfig($rebuilt);
    }

    private function isMarker(mixed $entry): bool
    {
        if (!$entry instanceof FilterInterface) {
            return false;
        }

        return true === $entry->getAsDto()->getCustomOption(BridgeOptions::IS_MARKER);
    }
}
