<?php

declare(strict_types=1);

namespace Polysource\Filter\EventListener;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies a `?view=<id>` saved view on Polysource-native index pages by
 * translating the SavedView's filters into the URL shape the
 * `Polysource\Bundle` AdminContextResolver decodes
 * (`?filter[name][op]=...&[value]=...`) and redirecting to the clean URL.
 *
 * Lives in `polysource/filter` (not `polysource/symfony-bundle`) so the
 * Symfony bundle has zero compile-time dep on filter classes — hosts
 * installing only `polysource/symfony-bundle` for non-Polysource use
 * cases never autoload `Polysource\Filter\*`. The pre-v0.1.0 review
 * caught the original cross-package import and recommended this move.
 *
 * Triggers only when the host registered the listener AND the request
 * carries a `resourceName` attribute (set by the AdminContextResolver
 * upstream of this priority). On other requests it short-circuits.
 *
 * Mirrors the EasyAdmin bridge's SavedViewApplySubscriber but for
 * Polysource-native routes — both translate `?view=<id>` into the
 * filter URL shape their respective hosts decode.
 */
final class PolysourceSavedViewApplyListener implements EventSubscriberInterface
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
        // Drop `view` (replaced with `filter`), `filter` (about to be rebuilt),
        // and `_t` (dropdown cache-buster — opaque, must not survive into the
        // canonical bookmarkable URL).
        unset($existing['view'], $existing['filter'], $existing['_t']);

        $existing['filter'] = self::collectionToUrlFilters($collection);

        $url = $request->getPathInfo() . '?' . http_build_query($existing);

        $response = new RedirectResponse($url);
        // Defensive: forbid caching of this 302 — see the EA-bridge
        // SavedViewApplySubscriber for the same rationale. Stale 200
        // of `?view=<id>` from a prior session can shadow the live
        // redirect and the user perceives "first click does nothing".
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
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
