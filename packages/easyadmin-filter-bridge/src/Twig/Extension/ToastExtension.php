<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Stringable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_toasts()` — renders the
 * Symfony flash messages as Bootstrap-styled toast notifications
 * in the top-right corner of the page.
 *
 * Per ADR-027 progressive enhancement: the helper deliberately
 * uses the Bootstrap `.alert` component (server-renderable,
 * always visible) rather than `.toast` (designed to require JS
 * to show). The visual placement mimics a toast (fixed top-right
 * stack) so the UX feels toast-like, but the messages display
 * without any client-side scripting. With JS loaded, the close
 * button (`data-bs-dismiss="alert"`) becomes interactive.
 *
 * Reads from the standard Symfony flash bag — so EA's own bulk
 * actions (which set `success`/`warning` flashes) are surfaced
 * out of the box. Hosts who set custom flash types (e.g.
 * `info`, `notice`) get the same treatment.
 *
 * Usage in a host EA index template override (typically in the
 * layout `main` block once, since flashes are page-wide):
 *
 *     {% block main %}
 *         {{ polysource_toasts() }}
 *         {{ parent() }}
 *     {% endblock %}
 *
 * Flash types are mapped to Bootstrap alert variants:
 *
 *   - success            → alert-success (green)
 *   - error / danger     → alert-danger (red)
 *   - warning            → alert-warning (yellow)
 *   - info / notice / *  → alert-info (blue)
 *
 * @since 0.5.0
 */
final class ToastExtension extends AbstractExtension
{
    use TranslatorFallbackTrait;

    /**
     * z-index reserved for floating notifications — must sit
     * above modals (1050+) and dropdowns (1000+) so bulk-action
     * confirmations remain visible after a modal closes.
     */
    private const Z_INDEX = 1080;

    /**
     * @var array<string, string> map from flash type → Bootstrap
     *                            alert variant CSS class
     */
    private const VARIANT_BY_TYPE = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'danger' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info',
        'notice' => 'alert-info',
    ];

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
                'polysource_toasts',
                $this->render(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function render(): Markup
    {
        $flashBag = $this->resolveFlashBag();
        if (null === $flashBag) {
            return new Markup('', 'UTF-8');
        }

        /** @var array<string, list<string>> $allFlashes */
        $allFlashes = $flashBag->all();
        if ([] === $allFlashes) {
            return new Markup('', 'UTF-8');
        }

        $textClose = htmlspecialchars(
            $this->transWithFallback('polysource.toasts.close', 'Close'),
            \ENT_QUOTES | \ENT_HTML5,
            'UTF-8',
        );
        $items = [];
        foreach ($allFlashes as $type => $messages) {
            $variant = self::VARIANT_BY_TYPE[$type] ?? 'alert-info';
            foreach ($messages as $message) {
                if (!\is_scalar($message) && !$message instanceof Stringable) {
                    continue;
                }
                $escaped = htmlspecialchars((string) $message, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                $items[] = <<<HTML
                    <div class="alert {$variant} alert-dismissible polysource-toast" role="alert">
                        {$escaped}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{$textClose}"></button>
                    </div>
                    HTML;
            }
        }

        if ([] === $items) {
            return new Markup('', 'UTF-8');
        }

        $z = self::Z_INDEX;
        $body = implode("\n", $items);
        $html = <<<HTML
            <div class="polysource-toast-container position-fixed top-0 end-0 p-3" style="z-index: {$z};" aria-live="polite" aria-atomic="true">
            {$body}
            </div>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    private function resolveFlashBag(): ?FlashBagInterface
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return null;
        }
        $session = $request->getSession();
        if (!$session instanceof Session) {
            return null;
        }

        return $session->getFlashBag();
    }
}
