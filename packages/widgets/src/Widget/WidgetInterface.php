<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

/**
 * One self-contained dashboard component — KPI counter, top-N
 * list, sparkline, custom rendering.
 *
 * Per ADR-022 §2: 5 methods, contract-by-composition. Widgets
 * carry their own data + their own template path. The Twig
 * `render_widget()` helper just resolves the template and passes
 * `getViewData()` in.
 *
 * Widgets are services in DI. Hosts compose them inside
 * {@see \Polysource\Widgets\Dashboard\Dashboard} value objects;
 * one widget instance can appear in several dashboards.
 */
interface WidgetInterface
{
    /**
     * Stable id used as the Bootstrap card anchor + audit hint.
     * Recommended: kebab-case domain prefix
     * (`mrr-counter`, `last-failed-messages`, `latency-sparkline`).
     */
    public function getId(): string;

    /**
     * Human-readable title rendered above the widget body.
     */
    public function getTitle(): string;

    /**
     * Bootstrap 5 column span (1-12). Default `4` = 3 widgets per
     * row. Counter widgets default to `3` (4-up grid), chart
     * widgets to `6` (2-up grid).
     */
    public function getColumnSpan(): int;

    /**
     * Twig template path. Concrete widgets typically point at
     * `@PolysourceWidgets/widgets/<type>.html.twig` but hosts
     * shipping custom widgets put their template anywhere.
     */
    public function getTemplate(): string;

    /**
     * Data dict merged into the Twig render context. The widget
     * itself is also exposed under the `widget` key so templates
     * can read `widget.title` / `widget.id` without duplicating
     * data here.
     *
     * @return array<string, mixed>
     */
    public function getViewData(): array;
}
