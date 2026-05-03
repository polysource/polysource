import { Controller } from '@hotwired/stimulus';

/**
 * polysource--filter — Stimulus controller for the EasyAdmin filter bridge.
 *
 * Bound to every wrapper rendered by `@PolysourceEasyAdminFilterBridge`
 * form theme blocks. Reads option values from `data-polysource--filter-*-value`
 * attrs and dispatches handlers when the user clicks preset buttons,
 * quick-range buttons, the clear button, etc.
 *
 * The controller is a single class shared by all 8 enhanced filter
 * types — it auto-detects which inputs exist inside its scope (via
 * name suffixes `[comparison]`, `[value]`, `[value2]`) and applies
 * actions only when the targeted inputs are present.
 *
 * Targets are NOT declared via `data-polysource--filter-target=…` because
 * the upstream EA blocks render the inputs without any way to inject
 * those attributes. Instead we lazily query them on each action call.
 *
 * @see Resources/views/form/polysource_filter_theme.html.twig
 */
export default class extends Controller {
    static values = {
        // numeric
        step: Number,
        quickRanges: { type: Array, default: [] },
        // datetime
        presets: { type: Array, default: [] },
        showClear: Boolean,
        // text
        minLength: Number,
        // boolean
        includeNull: Boolean,
        // choice
        inline: Boolean,
        // array
        chipDisplay: Boolean,
        // entity
        placeholder: String,
        // comparison
        comparisons: { type: Array, default: [] },
    };

    /**
     * Apply a date preset (e.g. "today", "last_7_days", "this_month") to
     * the filter inputs.
     *
     * Triggered by `data-action="polysource--filter#applyPreset"` with
     * `data-polysource--filter-preset-param="<name>"`.
     */
    applyPreset(event) {
        event.preventDefault();
        const preset = event.params.preset;
        const range = this.#computePresetRange(preset);
        if (range === null) {
            return; // unknown preset, custom date picker, etc.
        }

        const [from, to] = range;
        const { comparison, value, value2 } = this.#queryInputs();
        if (!comparison || !value) {
            return;
        }

        if (to !== null && value2) {
            this.#setSelectValue(comparison, 'between');
            this.#setInputValue(value, this.#formatDateForInput(from, value));
            this.#setInputValue(value2, this.#formatDateForInput(to, value2));
        } else {
            this.#setSelectValue(comparison, '=');
            this.#setInputValue(value, this.#formatDateForInput(from, value));
            if (value2) {
                this.#setInputValue(value2, '');
            }
        }

        // Trigger a `change` on comparison so the upstream value2 d-none
        // toggle in app.js fires.
        comparison.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Apply a numeric quick-range (min/max) to the filter inputs.
     *
     * Triggered by `data-action="polysource--filter#applyQuickRange"` with
     * `data-polysource--filter-min-param` and `data-polysource--filter-max-param`.
     * If both bounds are set → `between`. If only min → `>=`. If only max → `<=`.
     */
    applyQuickRange(event) {
        event.preventDefault();
        const minRaw = event.params.min;
        const maxRaw = event.params.max;
        const hasMin = minRaw !== '' && minRaw !== null && minRaw !== undefined;
        const hasMax = maxRaw !== '' && maxRaw !== null && maxRaw !== undefined;

        const { comparison, value, value2 } = this.#queryInputs();
        if (!comparison || !value) {
            return;
        }

        if (hasMin && hasMax && value2) {
            this.#setSelectValue(comparison, 'between');
            this.#setInputValue(value, String(minRaw));
            this.#setInputValue(value2, String(maxRaw));
        } else if (hasMin) {
            this.#setSelectValue(comparison, '>=');
            this.#setInputValue(value, String(minRaw));
            if (value2) this.#setInputValue(value2, '');
        } else if (hasMax) {
            this.#setSelectValue(comparison, '<=');
            this.#setInputValue(value, String(maxRaw));
            if (value2) this.#setInputValue(value2, '');
        }

        comparison.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Clear the value (and value2 if present) without resetting comparison.
     *
     * Triggered by `data-action="polysource--filter#clearValues"`.
     */
    clearValues(event) {
        event.preventDefault();
        const { value, value2 } = this.#queryInputs();
        if (value) this.#setInputValue(value, '');
        if (value2) this.#setInputValue(value2, '');
    }

    // -------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------

    /**
     * Locate the comparison/value/value2 inputs within this controller's
     * scope. Uses `name$=` suffix matching since EasyAdmin renders the
     * fields with `filters[<property>][comparison]`, `[value]`, `[value2]`.
     */
    #queryInputs() {
        return {
            comparison: this.element.querySelector('select[name$="[comparison]"], input[name$="[comparison]"]'),
            value: this.element.querySelector('input[name$="[value]"], select[name$="[value]"], textarea[name$="[value]"]'),
            value2: this.element.querySelector('input[name$="[value2]"], select[name$="[value2]"], textarea[name$="[value2]"]'),
        };
    }

    #setSelectValue(select, val) {
        select.value = val;
    }

    #setInputValue(input, val) {
        input.value = val;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * @returns {[Date, Date|null] | null}
     */
    #computePresetRange(preset) {
        const today = this.#startOfDay(new Date());

        switch (preset) {
            case 'today':
                return [today, null];

            case 'yesterday': {
                const y = new Date(today);
                y.setDate(y.getDate() - 1);
                return [y, null];
            }

            case 'last_7_days': {
                const from = new Date(today);
                from.setDate(from.getDate() - 6);
                return [from, today];
            }

            case 'last_30_days': {
                const from = new Date(today);
                from.setDate(from.getDate() - 29);
                return [from, today];
            }

            case 'this_month': {
                const from = new Date(today.getFullYear(), today.getMonth(), 1);
                return [from, today];
            }

            case 'last_month': {
                const from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const to = new Date(today.getFullYear(), today.getMonth(), 0);
                return [from, to];
            }

            case 'this_year': {
                const from = new Date(today.getFullYear(), 0, 1);
                return [from, today];
            }

            default:
                return null;
        }
    }

    #startOfDay(date) {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    /**
     * Format a Date for an HTML input. Detects the input type:
     *   - `date`         → "YYYY-MM-DD"
     *   - `datetime-local` → "YYYY-MM-DDTHH:mm"
     *   - anything else → ISO 8601 (UTC)
     */
    #formatDateForInput(date, input) {
        const type = (input.getAttribute('type') || '').toLowerCase();
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');

        if (type === 'date') {
            return `${yyyy}-${mm}-${dd}`;
        }

        const HH = String(date.getHours()).padStart(2, '0');
        const MM = String(date.getMinutes()).padStart(2, '0');

        if (type === 'datetime-local') {
            return `${yyyy}-${mm}-${dd}T${HH}:${MM}`;
        }

        // Fallback — ISO with seconds.
        const SS = String(date.getSeconds()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}T${HH}:${MM}:${SS}`;
    }
}
