<?php

declare(strict_types=1);

namespace Polysource\Audit\Serializer;

use DateTimeInterface;
use Stringable;

/**
 * Coerces arbitrary Doctrine column values into JSON-friendly
 * primitives so they can be stored as part of the audit-log
 * `context.changes` / `context.snapshot` payloads without leaking
 * entity references or breaking encoding.
 *
 * Strategy (top-down — first match wins):
 *   1. null / scalar — passed through verbatim
 *   2. DateTimeInterface → ISO-8601 ATOM string
 *   3. Stringable → __toString
 *   4. array → recursive serialise
 *   5. other object → '[object Fully\Qualified\ClassName]' marker
 *   6. fallthrough → null (defensive)
 *
 * Extracted from `EasyAdminAuditSubscriber::serialiseValue()` in
 * v0.10.0 per audit task #68 — the subscriber is now thin and the
 * serialisation logic is unit-testable in isolation.
 *
 * @since 0.10.0
 */
final class AuditValueSerializer
{
    public static function serialise(mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            return array_map(self::serialise(...), $value);
        }

        if (\is_object($value)) {
            return '[object ' . $value::class . ']';
        }

        return null;
    }
}
