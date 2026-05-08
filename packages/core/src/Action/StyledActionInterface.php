<?php

declare(strict_types=1);

namespace Polysource\Core\Action;

/**
 * Optional contract an action implements when it wants to control its
 * UI rendering hints — Bootstrap variant for the button (primary,
 * secondary, danger, warning, success, info, light, dark) and an
 * optional confirmation prompt shown before the action runs.
 *
 * Why this lives outside {@see ActionInterface}: the base contract
 * stays minimal (5 methods, ADR-010 surface budget). UI hints are
 * a presentation concern that not every action needs — a host
 * shipping JSON-only action results does not need either method.
 *
 * Templates that consume the index/detail action lists fall back to
 * `'secondary'` and `null` when an action does not implement this
 * interface, keeping rendering decisions out of the template (the
 * pre-v0.1.0 review caught the previous magic-string coupling
 * between the generic theme and adapter-specific action names like
 * `'purge'` and `'dismiss'`).
 */
interface StyledActionInterface extends ActionInterface
{
    /**
     * Bootstrap 5 variant key WITHOUT the `btn-` prefix. Common values:
     * `primary`, `secondary`, `success`, `danger`, `warning`, `info`,
     * `light`, `dark`. Themes are free to map this to other systems
     * (Tailwind, Bulma, your design system) — the contract is the
     * vocabulary, not the CSS.
     */
    public function getCssVariant(): string;

    /**
     * Confirmation text shown to the user before the action executes.
     * The theme renders it as a JS `confirm()` prompt by default;
     * advanced hosts can override the template to render a modal.
     * Return `null` when the action runs immediately without prompting.
     */
    public function getConfirmation(): ?string;
}
