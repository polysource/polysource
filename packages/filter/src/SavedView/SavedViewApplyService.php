<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView;

use Polysource\Filter\SavedView\Model\SavedView;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Shared core of the `?view=<id>` saved-view-apply flow.
 *
 * Two listeners trigger on different events:
 *
 *   - `Polysource\EasyAdminFilterBridge\EventListener\SavedViewApplySubscriber`
 *     fires on EasyAdmin's `BeforeCrudActionEvent` and emits the EA
 *     URL shape (`filters[<prop>][comparison]=<op>&[value]=<v>`).
 *   - `Polysource\Filter\EventListener\PolysourceSavedViewApplyListener`
 *     fires on Symfony's `KernelEvents::REQUEST` for Polysource-native
 *     admin routes and emits the Polysource URL shape
 *     (`filter[<prop>][op]=<op>&[value]=<v>`).
 *
 * Both share the same orchestration: load the view by id, refuse it
 * if the resource name doesn't match the current page, build a
 * cache-busting redirect to the canonical filtered URL. That core
 * lives here. The shape-specific URL translation stays in each
 * listener because the matrices are genuinely different (EA needs
 * per-filter FormType awareness for ChoiceFilter+multiple cases;
 * Polysource standalone has no such constraint).
 *
 * Extracted from `SavedViewApplySubscriber` + `PolysourceSavedViewApplyListener`
 * in v0.10.0 per audit task #66 (MEDIUM DRY).
 *
 * @since 0.10.0
 */
final class SavedViewApplyService
{
    public function __construct(private readonly SavedViewService $service)
    {
    }

    /**
     * Resolve a `?view=<id>` parameter to a {@see SavedView} suitable
     * for application on the current page.
     *
     * Returns null when:
     *   - the id is empty (no `?view=` in the URL)
     *   - the view doesn't exist OR the user has no VIEW permission
     *     (SavedViewService::load() runs the voter)
     *   - the view's resourceName doesn't match the caller's expected
     *     resource (stale link / shared URL across resources)
     *
     * In each null case the listener should fall through to the
     * default page rendering rather than redirect.
     */
    public function resolveView(string $viewId, string $expectedResource): ?SavedView
    {
        if ('' === $viewId || '' === $expectedResource) {
            return null;
        }

        $view = $this->service->load($viewId);
        if (null === $view || $view->resourceName !== $expectedResource) {
            return null;
        }

        return $view;
    }

    /**
     * Build the cache-busting redirect both listeners use to land the
     * user on the canonical filtered URL. The query is rendered with
     * `http_build_query` — caller is responsible for any URL-shape
     * encoding (EA expanded vs Polysource native) prior to this call.
     *
     * The Cache-Control + Pragma headers are essential: without them
     * a stale 200 from a prior `?view=<id>` request can shadow this
     * 302, producing the "first click does nothing" symptom that
     * surfaced as a pre-v0.1.0 demo bug.
     *
     * @param array<int|string, mixed> $query the full request query, with
     *                                        `view` already stripped and
     *                                        the filter slice merged in
     */
    public function buildRedirect(string $path, array $query): RedirectResponse
    {
        $url = [] === $query
            ? $path
            : $path . '?' . http_build_query($query);

        $response = new RedirectResponse($url);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
