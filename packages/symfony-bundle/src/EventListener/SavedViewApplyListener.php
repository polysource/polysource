<?php

declare(strict_types=1);

namespace Polysource\Bundle\EventListener;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies a `?view=<id>` saved view on Polysource index pages by
 * translating the SavedView's filters into the URL shape the
 * AdminContextResolver decodes (`?filter[name][op]=...&[value]=...`)
 * and redirecting to the clean URL.
 *
 * Without this listener, clicking a saved view in the dropdown lands
 * on `/admin/polysource/<resource>?view=<id>` with no filters applied
 * — the resolver would simply ignore the unknown `view` query param.
 *
 * Mirrors the EasyAdmin bridge's SavedViewApplySubscriber but for
 * Polysource-native routes. Conditionally registered (cf.
 * services.php) so the bundle has no hard dep on polysource/filter.
 */
final class SavedViewApplyListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?SavedViewService $service = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority < 8 so this fires AFTER:
        //   - Symfony's RouterListener (priority 32) which populates
        //     the `resourceName` request attribute, AND
        //   - Symfony Security's FirewallListener (priority 8) which
        //     sets the auth token. SavedViewService::load() runs the
        //     SavedViewVoter, which is anonymous-hostile — we need a
        //     resolved token before calling it. Higher priority would
        //     silently fail every load.
        // Still well above the controller invocation, so we can
        // short-circuit with a redirect instead of invoking the
        // controller and re-rendering.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->service) {
            return;
        }

        $request = $event->getRequest();
        $viewId = (string) $request->query->get('view', '');
        if ('' === $viewId) {
            return;
        }

        $resourceName = $request->attributes->get('resourceName');
        if (!\is_string($resourceName) || '' === $resourceName) {
            return;
        }

        $view = $this->service->load($viewId);
        if (null === $view || $view->resourceName !== $resourceName) {
            // Either the view has been deleted, or it belongs to a
            // different resource (stale link / shared URL across
            // resources). Drop the param silently.
            return;
        }

        $event->setResponse($this->buildRedirect($request, $view->filters));
    }

    private function buildRedirect(Request $request, FilterCollection $collection): RedirectResponse
    {
        $existing = $request->query->all();
        unset($existing['view'], $existing['filter']);

        $existing['filter'] = self::collectionToUrlFilters($collection);

        $url = $request->getPathInfo() . '?' . http_build_query($existing);

        return new RedirectResponse($url);
    }

    /**
     * @return array<string, array{op: string, value?: string, values?: list<string>, min?: string, max?: string}>
     */
    private static function collectionToUrlFilters(FilterCollection $collection): array
    {
        $out = [];
        foreach ($collection as $criterion) {
            $entry = ['op' => $criterion->operator];

            $values = $criterion->values;
            $stringify = static fn ($v): string => \is_scalar($v) ? (string) $v : '';

            if ('between' === $criterion->operator && 2 === \count($values)) {
                $entry['min'] = $stringify($values[0]);
                $entry['max'] = $stringify($values[1]);
            } elseif (1 === \count($values)) {
                $entry['value'] = $stringify($values[0]);
            } elseif (\count($values) > 0) {
                $entry['values'] = array_map($stringify, $values);
            }

            $out[$criterion->property] = $entry;
        }

        return $out;
    }
}
