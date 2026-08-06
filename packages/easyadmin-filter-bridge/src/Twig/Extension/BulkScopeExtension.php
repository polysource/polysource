<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig helpers for cross-page selection + bulk dry-run (v0.4.0 Task #19).
 *
 * Bulk actions in EA default to operating on the CURRENT PAGE's
 * selected rows. When a user filters down a large dataset and
 * wants to apply an action to EVERY matching row (not just page 1),
 * they need a different scope signal. This extension provides:
 *
 *   - `polysource_bulk_scope_toggle(action_url, label)` — renders a
 *     checkbox that, when checked, switches the bulk action's scope
 *     from "selected rows on this page" to "all rows matching the
 *     current filter slice". Implementation: a small `<input
 *     type="checkbox" name="bulk_scope" value="all_matching">` the
 *     host's bulk action endpoint reads.
 *
 *   - `polysource_bulk_dry_run_url(action_url)` — appends
 *     `?dry_run=1` to the host's bulk-action endpoint URL. Calls to
 *     this URL should return a JSON preview (count + sample rows)
 *     instead of executing. Polysource ships no controller for the
 *     dry-run endpoint — the contract is documented; hosts
 *     implement it per resource.
 *
 * Polysource doesn't ship the COUNT logic (it would require
 * integrating with EA's filter-aware QueryBuilder construction,
 * deferred to v0.5+). Hosts who want a real dry-run preview wire
 * their own controller responding to `?dry_run=1` with a JSON
 * payload like `{count: 1234, samples: [...]}`.
 *
 * @since 0.4.0
 */
final class BulkScopeExtension extends AbstractExtension
{
    use TranslatorFallbackTrait;

    public function __construct(
        private readonly ?RequestStack $requestStack = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_bulk_scope_toggle',
                $this->renderScopeToggle(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction('polysource_bulk_dry_run_url', $this->dryRunUrl(...)),
            new TwigFunction(
                'polysource_bulk_scope_active',
                $this->scopeActive(...),
            ),
        ];
    }

    public function renderScopeToggle(?string $label = null): Markup
    {
        $label ??= $this->transWithFallback('polysource.bulk.apply_all', 'Apply to all matching rows');
        $labelEsc = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $checked = $this->scopeActive() ? ' checked' : '';

        $html = <<<HTML
            <label class="polysource-bulk-scope-toggle form-check">
                <input class="form-check-input"
                       type="checkbox"
                       name="bulk_scope"
                       value="all_matching"{$checked}>
                <span class="form-check-label">{$labelEsc}</span>
            </label>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    public function scopeActive(): bool
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        return 'all_matching' === (string) $request->request->get('bulk_scope', '');
    }

    /**
     * Append `?dry_run=1` to the given URL (or `&dry_run=1` if it
     * already carries a query string). The host's bulk-action
     * endpoint should branch on this and return a JSON preview
     * instead of executing.
     */
    public function dryRunUrl(string $actionUrl): string
    {
        $separator = str_contains($actionUrl, '?') ? '&' : '?';

        return $actionUrl . $separator . 'dry_run=1';
    }
}
