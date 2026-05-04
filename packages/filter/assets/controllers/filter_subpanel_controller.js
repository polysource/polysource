import { Controller } from '@hotwired/stimulus';

/**
 * polysource--filter-subpanel — sliding side panel UX for the
 * subpanel filter mode.
 *
 * Actions:
 *
 * - `open` — adds the `show` class to the panel + a `polysource-filter-subpanel-open`
 *   class on `<body>` (host CSS hooks for backdrop / scroll lock).
 *   Sets focus on the first focusable element inside the panel for
 *   keyboard accessibility.
 *
 * - `close` — removes the show classes, restores focus to the trigger
 *   button, removes the ESC handler.
 *
 * - `switchTab` — given a `data-polysource--filter-subpanel-target-param="<panel-id>"`
 *   on a tab button, hides every other tabpane and shows the targeted one,
 *   updates aria-selected on tab buttons.
 *
 * The panel toggles `show` (Bootstrap convention) so hosts can rely on
 * Bootstrap's CSS for the slide animation; if the host is not on
 * Bootstrap, the controller still works — the `show` class is just an
 * arbitrary marker our host CSS can hook on.
 */
export default class extends Controller {
    static targets = ['panel', 'tablist', 'tabpane'];

    static values = {
        panelId: String,
    };

    #lastTrigger = null;
    #onKeydown = null;

    /** @param {Event} event */
    open(event) {
        event.preventDefault();
        if (!this.hasPanelTarget) return;

        this.#lastTrigger = event.currentTarget;
        this.panelTarget.classList.add('show');
        this.panelTarget.removeAttribute('aria-hidden');
        document.body.classList.add('polysource-filter-subpanel-open');

        this.#focusFirstInteractive();

        this.#onKeydown = (e) => {
            if (e.key === 'Escape') this.close(e);
        };
        document.addEventListener('keydown', this.#onKeydown);
    }

    /** @param {Event} event */
    close(event) {
        event.preventDefault();
        if (!this.hasPanelTarget) return;

        this.panelTarget.classList.remove('show');
        this.panelTarget.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('polysource-filter-subpanel-open');

        if (this.#onKeydown) {
            document.removeEventListener('keydown', this.#onKeydown);
            this.#onKeydown = null;
        }

        this.#lastTrigger?.focus?.();
        this.#lastTrigger = null;
    }

    /**
     * @param {Event & {params: {target: string}}} event
     */
    switchTab(event) {
        event.preventDefault();
        const targetId = event.params.target;
        if (!targetId) return;

        // Update tab button aria-selected + active class.
        if (this.hasTablistTarget) {
            const buttons = this.tablistTarget.querySelectorAll('[role="tab"]');
            buttons.forEach((button) => {
                const isClicked = button === event.currentTarget;
                button.classList.toggle('active', isClicked);
                button.setAttribute('aria-selected', String(isClicked));
            });
        }

        // Show/hide the matching tabpane.
        this.tabpaneTargets.forEach((pane) => {
            const isMatch = pane.id === targetId;
            pane.classList.toggle('show', isMatch);
            pane.classList.toggle('active', isMatch);
        });
    }

    disconnect() {
        if (this.#onKeydown) {
            document.removeEventListener('keydown', this.#onKeydown);
            this.#onKeydown = null;
        }
    }

    #focusFirstInteractive() {
        const focusable = this.panelTarget.querySelector(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );
        focusable?.focus?.();
    }
}
