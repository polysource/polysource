<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain value object — a short token that resolves back to a
 * full `?filters[...]` URL slice + its resource scope.
 *
 * Use case: bookmark / share / email a long filter URL via a
 * short `/admin/polysource/f/{token}` redirect.
 *
 * Immutable. Built via the constructor.
 *
 * @since 0.5.0
 */
final class FilterUrlToken
{
    /**
     * Strict pattern for tokens. Hex-lowercase, 12 chars
     * (collision space = 2^48, comfortable for the small
     * volumes expected).
     */
    public const TOKEN_PATTERN = '/^[a-f0-9]{12}$/';

    /**
     * @param string               $token        The opaque identifier
     * @param string               $resourceName Logical resource scope
     * @param array<string, mixed> $filtersSlice The decoded `?filters[...]` slice
     * @param DateTimeImmutable    $createdAt    When the token was minted
     */
    public function __construct(
        public readonly string $token,
        public readonly string $resourceName,
        public readonly array $filtersSlice,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if (1 !== preg_match(self::TOKEN_PATTERN, $token)) {
            throw new InvalidArgumentException(\sprintf('FilterUrlToken token must match %s, got %s.', self::TOKEN_PATTERN, var_export($token, true)));
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('FilterUrlToken resourceName cannot be empty.');
        }
        if ([] === $filtersSlice) {
            throw new InvalidArgumentException('FilterUrlToken filtersSlice cannot be empty — empty slices need no short URL.');
        }
    }
}
