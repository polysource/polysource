import { Controller } from '@hotwired/stimulus';

/**
 * polysource--filter — Stimulus controller for the EasyAdmin filter bridge.
 *
 * Bound to every wrapper rendered by `@PolysourceEasyAdminFilterBridge`
 * form theme blocks. Reads option values from `data-polysource--filter-*-value`
 * attrs.
 *
 * Since v0.2.0 the controller is value-only — no action methods.
 * Earlier versions (v0.1.x) shipped `applyPreset`, `applyQuickRange`,
 * and `clearValues` action handlers for the `presets`, `quick_ranges`,
 * and `show_clear` form options. Those options were removed per ADR-027
 * (progressive enhancement — baseline must work without JS) + ADR-028
 * (scope discipline). The remaining `static values` schema is kept so
 * host applications that extend this controller (or wire their own
 * progressive-enhancement layer) can read the same typed data attrs
 * Polysource already produces.
 *
 * If you don't extend this controller from a host-side JS layer, the
 * `data-controller="polysource--filter"` binding on the bridge's form
 * theme is a no-op — its presence won't break the page.
 *
 * @see Resources/views/form/polysource_filter_theme.html.twig
 */
export default class extends Controller {
    static values = {
        // numeric
        step: Number,
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
}
