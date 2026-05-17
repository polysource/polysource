<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Polysource\Filter\Url\FilterArrayExtractor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_filter_short_url(resource)`
 * — mints a short token for the current request's filter slice
 * and returns the bridge's `/admin/polysource/f/{token}`
 * redirect URL.
 *
 * Use case: a "Copy share link" button next to the active
 * filters chip bar. The host renders the helper output as a
 * `<button data-clipboard-text="...">` (or similar) and a
 * follow-up click on the recipient's machine resolves back to
 * the full filter URL.
 *
 * Returns an empty string when there are no active filters —
 * no point shortening "no filters". Same for when the bridge's
 * FilterUrlTokenService isn't wired (host without Doctrine).
 *
 * @since 0.5.0
 */
final class FilterShortUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?FilterUrlTokenService $service = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_filter_short_url',
                $this->shortUrl(...),
            ),
            new TwigFunction(
                'polysource_filter_share_button',
                $this->renderShareButton(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * Build the short URL for the current request's filter slice.
     * Returns an empty string when no filters are active or when
     * the service isn't wired.
     */
    public function shortUrl(string $resource): string
    {
        if (null === $this->service || null === $this->urlGenerator || null === $this->requestStack) {
            return '';
        }
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return '';
        }

        // Extracted to FilterArrayExtractor in v0.9.0 — the
        // defensive `is_array($query['filters'])` + non-string-key
        // sift was repeated verbatim across multiple sites.
        $filters = FilterArrayExtractor::fromQueryArray($request->query->all());
        if ([] === $filters) {
            return '';
        }

        $token = $this->service->tokenize($resource, $filters);
        if (null === $token) {
            return '';
        }

        return $this->urlGenerator->generate(
            'polysource_filter_url_token_resolve',
            [
                'token' => $token->token,
                'index' => $request->getPathInfo(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /**
     * Render a Bootstrap "Copy share link" button. The short URL
     * is embedded in `data-clipboard-text` for the host's
     * clipboard JS (or for the user to right-click + copy link
     * address). Empty markup when no filters / no service wired.
     */
    public function renderShareButton(string $resource, string $label = 'Copy share link'): Markup
    {
        $url = $this->shortUrl($resource);
        if ('' === $url) {
            return new Markup('', 'UTF-8');
        }

        $urlEscaped = htmlspecialchars($url, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $labelEscaped = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $html = <<<HTML
            <a href="{$urlEscaped}"
               class="btn btn-sm btn-outline-secondary polysource-filter-share"
               data-polysource-share-url="{$urlEscaped}"
               aria-label="{$labelEscaped}">
                {$labelEscaped}
            </a>
            HTML;

        return new Markup($html, 'UTF-8');
    }
}
