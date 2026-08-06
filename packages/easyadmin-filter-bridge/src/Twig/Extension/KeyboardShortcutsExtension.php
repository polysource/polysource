<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Contracts\Translation\TranslatorInterface;
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
    use TranslatorFallbackTrait;

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    /**
     * @var list<array{key: string, id: string, label: string, scope_id: string, scope: string}>
     *      the recommended shortcut set — `label`/`scope` are the
     *      English fallbacks, `id`/`scope_id` the translation-key
     *      suffixes under `polysource.shortcuts.*`. Hosts consume
     *      the translated list via {@see shortcutsList()}.
     */
    private const RECOMMENDED_SHORTCUTS = [
        ['key' => 'j',     'id' => 'next_row',       'label' => 'Next row',                     'scope_id' => 'listing', 'scope' => 'Listing'],
        ['key' => 'k',     'id' => 'previous_row',   'label' => 'Previous row',                 'scope_id' => 'listing', 'scope' => 'Listing'],
        ['key' => 'Enter', 'id' => 'open_row',       'label' => 'Open the focused row',         'scope_id' => 'listing', 'scope' => 'Listing'],
        ['key' => '/',     'id' => 'focus_search',   'label' => 'Focus the search field',       'scope_id' => 'search',  'scope' => 'Search'],
        ['key' => 'f',     'id' => 'open_filters',   'label' => 'Open the filters modal',       'scope_id' => 'filters', 'scope' => 'Filters'],
        ['key' => 'c',     'id' => 'toggle_columns', 'label' => 'Toggle column visibility menu', 'scope_id' => 'columns', 'scope' => 'Columns'],
        ['key' => 'n',     'id' => 'create_record',  'label' => 'Create new record',            'scope_id' => 'actions', 'scope' => 'Actions'],
        ['key' => '?',     'id' => 'toggle_help',    'label' => 'Toggle this help panel',       'scope_id' => 'help',    'scope' => 'Help'],
        ['key' => 'Esc',   'id' => 'close_modal',    'label' => 'Close any open modal / panel', 'scope_id' => 'global',  'scope' => 'Global'],
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
        $out = [];
        foreach (self::RECOMMENDED_SHORTCUTS as $shortcut) {
            $out[] = [
                'key' => $shortcut['key'],
                'label' => $this->transWithFallback('polysource.shortcuts.action.' . $shortcut['id'], $shortcut['label']),
                'scope' => $this->transWithFallback('polysource.shortcuts.scope.' . $shortcut['scope_id'], $shortcut['scope']),
            ];
        }

        return $out;
    }

    public function renderHelp(): Markup
    {
        $rows = '';
        foreach ($this->shortcutsList() as $shortcut) {
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

        $esc = static fn (string $v): string => htmlspecialchars($v, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $textTitle = $esc($this->transWithFallback('polysource.shortcuts.title', 'Keyboard shortcuts (recommended)'));
        $textKey = $esc($this->transWithFallback('polysource.shortcuts.header.key', 'Key'));
        $textAction = $esc($this->transWithFallback('polysource.shortcuts.header.action', 'Action'));
        $textScope = $esc($this->transWithFallback('polysource.shortcuts.header.scope', 'Scope'));
        $textFootnote = $esc($this->transWithFallback('polysource.shortcuts.footnote', 'Hosts wire the actual bindings via a Stimulus controller — see the Polysource docs for a reference snippet.'));

        $html = <<<HTML
            <details class="polysource-keyboard-shortcuts" data-polysource-shortcuts-help>
                <summary>{$textTitle}</summary>
                <table class="table table-sm polysource-keyboard-shortcuts__table">
                    <thead>
                        <tr><th scope="col">{$textKey}</th><th scope="col">{$textAction}</th><th scope="col">{$textScope}</th></tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                </table>
                <p class="text-muted small mb-0">
                    {$textFootnote}
                </p>
            </details>
            HTML;

        return new Markup($html, 'UTF-8');
    }
}
