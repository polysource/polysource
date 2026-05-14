<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing
 * `polysource_keyboard_shortcuts_help()` — renders a help
 * cheat-sheet for the recommended Polysource keyboard
 * shortcuts in a native HTML `<details>` element.
 *
 * Per ADR-027 progressive enhancement: the `<details>` element
 * is fully server-renderable and toggleable without JS — users
 * see the shortcut list whether or not the host loaded a
 * Stimulus controller. Actually binding the shortcuts (j/k to
 * navigate, / to focus search, etc.) is a host-side concern;
 * the bundle does not ship a JS controller (see
 * `docs/user/easyadmin-filter-bridge/keyboard-shortcuts.md`
 * for a reference Stimulus snippet).
 *
 * Usage in a host layout:
 *
 *     {% block main %}
 *         {{ parent() }}
 *         {{ polysource_keyboard_shortcuts_help() }}
 *     {% endblock %}
 *
 * The host opts into the JS layer by writing a Stimulus
 * controller (or vanilla JS) that:
 *
 *   - Reads `data-polysource-shortcut="..."` attributes on
 *     elements to discover what's bindable.
 *   - Hooks `keydown` on `document`, dispatches matching keys.
 *
 * Hosts who don't ship JS get the help panel + the existing
 * tab-and-enter navigation EA already provides.
 *
 * @since 0.5.0
 */
final class KeyboardShortcutsExtension extends AbstractExtension
{
    /**
     * @var list<array{key: string, label: string, scope: string}>
     *      the recommended shortcut set — hosts can use this list
     *      verbatim or as inspiration for their own bindings
     */
    private const RECOMMENDED_SHORTCUTS = [
        ['key' => 'j',     'label' => 'Next row',                     'scope' => 'Listing'],
        ['key' => 'k',     'label' => 'Previous row',                 'scope' => 'Listing'],
        ['key' => 'Enter', 'label' => 'Open the focused row',         'scope' => 'Listing'],
        ['key' => '/',     'label' => 'Focus the search field',       'scope' => 'Search'],
        ['key' => 'f',     'label' => 'Open the filters modal',       'scope' => 'Filters'],
        ['key' => 'c',     'label' => 'Toggle column visibility menu', 'scope' => 'Columns'],
        ['key' => 'n',     'label' => 'Create new record',            'scope' => 'Actions'],
        ['key' => '?',     'label' => 'Toggle this help panel',       'scope' => 'Help'],
        ['key' => 'Esc',   'label' => 'Close any open modal / panel', 'scope' => 'Global'],
    ];

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_keyboard_shortcuts_help',
                $this->renderHelp(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction(
                'polysource_keyboard_shortcuts_list',
                $this->shortcutsList(...),
            ),
        ];
    }

    /**
     * Return the recommended shortcut set as a list — useful for
     * hosts who want to render their own table or pass the list to
     * a JS controller as JSON.
     *
     * @return list<array{key: string, label: string, scope: string}>
     */
    public function shortcutsList(): array
    {
        return self::RECOMMENDED_SHORTCUTS;
    }

    public function renderHelp(): Markup
    {
        $rows = '';
        foreach (self::RECOMMENDED_SHORTCUTS as $shortcut) {
            $key = htmlspecialchars($shortcut['key'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $label = htmlspecialchars($shortcut['label'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $scope = htmlspecialchars($shortcut['scope'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $rows .= <<<HTML
                <tr>
                    <th scope="row"><kbd>{$key}</kbd></th>
                    <td>{$label}</td>
                    <td class="text-muted small">{$scope}</td>
                </tr>
                HTML;
        }

        $html = <<<HTML
            <details class="polysource-keyboard-shortcuts" data-polysource-shortcuts-help>
                <summary>Keyboard shortcuts (recommended)</summary>
                <table class="table table-sm polysource-keyboard-shortcuts__table">
                    <thead>
                        <tr><th scope="col">Key</th><th scope="col">Action</th><th scope="col">Scope</th></tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                </table>
                <p class="text-muted small mb-0">
                    Hosts wire the actual bindings via a Stimulus controller — see the Polysource docs for a reference snippet.
                </p>
            </details>
            HTML;

        return new Markup($html, 'UTF-8');
    }
}
