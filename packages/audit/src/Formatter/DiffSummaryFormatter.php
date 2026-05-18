<?php

declare(strict_types=1);

namespace Polysource\Audit\Formatter;

/**
 * Builds the human-readable single-line diff summary stored in
 * {@see \Polysource\Audit\Model\AuditEntry::$message}. The summary
 * appears in CSV exports, the audit-log dropdown, and tail-style
 * queries — it has to be short, scan-friendly, and information-dense.
 *
 * Three formats depending on action:
 *   - `create` — `field=value, field=value, …`
 *   - `update` — `field: 'old' → 'new', …`
 *   - `delete` — `field=value, …` (from the entity snapshot)
 *
 * The output is capped at {@see self::MAX_MESSAGE_BYTES}. The full
 * structured diff still goes into `context.changes` /
 * `context.snapshot` which has no length cap.
 *
 * Extracted from `EasyAdminAuditSubscriber` in v0.10.0 per audit
 * task #68. Stays a pure-function value formatter — no state,
 * easily unit-testable.
 *
 * @since 0.10.0
 */
final class DiffSummaryFormatter
{
    /**
     * Maximum length of the human-readable diff summary. Anything
     * longer is truncated with a trailing "… [truncated]".
     */
    public const MAX_MESSAGE_BYTES = 1024;

    /**
     * @param array<string, array{old: mixed, new: mixed}>|null $changes
     * @param array<string, mixed>|null                          $snapshot
     */
    public static function summarise(string $action, ?array $changes, ?array $snapshot): ?string
    {
        if ('delete' === $action && null !== $snapshot && [] !== $snapshot) {
            $parts = [];
            foreach ($snapshot as $field => $value) {
                $parts[] = $field . '=' . self::formatScalar($value);
            }

            return self::truncate(implode(', ', $parts));
        }

        if (null === $changes || [] === $changes) {
            return null;
        }

        $parts = [];
        foreach ($changes as $field => $delta) {
            if ('create' === $action) {
                $parts[] = $field . '=' . self::formatScalar($delta['new']);
                continue;
            }
            $parts[] = \sprintf(
                '%s: %s → %s',
                $field,
                self::formatScalar($delta['old']),
                self::formatScalar($delta['new']),
            );
        }

        return self::truncate(implode(', ', $parts));
    }

    /**
     * Format a single scalar value for embedding in the summary.
     * Strings are single-quoted, nulls/booleans use literal words,
     * arrays render as `array(N)` (the structured content is in
     * `context.changes`, not in the summary).
     */
    public static function formatScalar(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_string($value)) {
            return "'" . $value . "'";
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if (\is_array($value)) {
            return 'array(' . \count($value) . ')';
        }

        return '?';
    }

    private static function truncate(string $message): string
    {
        if (\strlen($message) <= self::MAX_MESSAGE_BYTES) {
            return $message;
        }

        return substr($message, 0, self::MAX_MESSAGE_BYTES) . '… [truncated]';
    }
}
