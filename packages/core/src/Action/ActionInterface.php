<?php

declare(strict_types=1);

namespace Polysource\Core\Action;

/**
 * Base contract for any action: inline, bulk, or global.
 *
 * Concrete actions implement one of:
 *   - {@see InlineActionInterface}: operates on one record
 *   - {@see BulkActionInterface}: operates on multiple records at once
 *
 * Or they declare themselves as a "global" action by implementing only
 * this base interface (e.g. "New product", "Export CSV", which don't
 * target an existing record).
 */
interface ActionInterface
{
    /**
     * Slug identifier, used in URLs (kebab-case recommended).
     */
    public function getName(): string;

    /**
     * Display label. May be a translatable key — the UI handles translation.
     */
    public function getLabel(): string;

    /**
     * Optional icon name (UI theme-dependent, e.g. "trash", "refresh").
     */
    public function getIcon(): ?string;

    /**
     * Permission attribute checked via the configured PermissionInterface.
     * Returns null if the action is publicly accessible to authenticated users.
     */
    public function getPermission(): ?string;

    /**
     * Whether this action is currently displayed in the UI.
     * Used to hide actions based on context (e.g. "Retry" should not appear
     * on already-succeeded records).
     *
     * @param array<string, mixed> $context arbitrary context (current record, etc.)
     */
    public function isDisplayed(array $context = []): bool;
}
