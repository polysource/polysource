import { Controller } from '@hotwired/stimulus';

/**
 * polysource--filter-chips — interactions for the chips bar above
 * a filtered list.
 *
 * Bound to the wrapper rendered by `chips.html.twig`. Two actions:
 *
 * - `removeChip` (click on a chip's X button): rebuilds the URL
 *   without the property's filter slice and navigates there. The
 *   server's session subscriber (FilterService.save) updates the
 *   stored collection accordingly on the next page.
 *
 * - `expandOverflow` (click on "+N more"): toggles the hidden
 *   overflow region's visibility. Pure DOM toggle, no navigation.
 *
 * The chips bar's own `data-property` attributes drive the URL
 * surgery — we strip every `filters[<property>][...]` query param
 * matching the chip the user clicked, then assign window.location
 * with the cleaned URL.
 */
export default class extends Controller {
    static targets = ['chip', 'overflowToggle', 'overflow'];

    static values = {
        collectionId: String,
    };

    /**
     * Click handler for a chip's X button.
     * @param {Event & {params: {property: string}}} event
     */
    removeChip(event) {
        event.preventDefault();
        const property = event.params.property;
        if (!property) return;

        const url = new URL(window.location.href);
        const params = url.searchParams;

        // Remove every `filters[<property>][...]` entry. URLSearchParams
        // doesn't allow regex deletion, so we collect keys first.
        const keysToDelete = [];
        for (const key of params.keys()) {
            if (key.startsWith(`filters[${property}]`)) {
                keysToDelete.push(key);
            }
        }
        for (const key of keysToDelete) {
            params.delete(key);
        }

        window.location.assign(url.toString());
    }

    /**
     * Toggles the overflow region and the toggle button's label.
     * @param {Event} event
     */
    expandOverflow(event) {
        event.preventDefault();
        if (!this.hasOverflowTarget) return;

        this.overflowTarget.classList.toggle('d-none');

        if (this.hasOverflowToggleTarget) {
            const expanded = !this.overflowTarget.classList.contains('d-none');
            this.overflowToggleTarget.setAttribute('aria-expanded', String(expanded));
        }
    }
}
