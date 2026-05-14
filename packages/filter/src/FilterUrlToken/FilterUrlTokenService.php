<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken;

use DateTimeImmutable;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;
use Polysource\Filter\FilterUrlToken\Storage\FilterUrlTokenStorageInterface;
use RuntimeException;

/**
 * Application service for short filter-URL tokens.
 *
 * Hosts call `tokenize()` to mint a short token for a given
 * filter slice, and `resolve()` to translate a token back to
 * the full slice when the user follows the short link.
 *
 * Tokens are 12-hex-char strings (`[a-f0-9]{12}` — collision
 * space 2^48). The service retries on collision; in the
 * astronomically unlikely event of repeated collisions, it
 * gives up and throws.
 *
 * The service does NOT enforce ownership — anyone with the token
 * can resolve it. Hosts who need ownership-gated short URLs
 * subclass and add their own check.
 *
 * @since 0.5.0
 */
final class FilterUrlTokenService
{
    /** Token-mint retry budget on collision. */
    private const MAX_COLLISIONS = 8;

    public function __construct(private readonly FilterUrlTokenStorageInterface $storage)
    {
    }

    /**
     * Mint a token for the given filter slice. If `$filtersSlice`
     * is empty, returns `null` — no point shortening "no filters".
     *
     * @param array<string, mixed> $filtersSlice
     */
    public function tokenize(string $resourceName, array $filtersSlice): ?FilterUrlToken
    {
        if ([] === $filtersSlice) {
            return null;
        }

        for ($attempt = 0; $attempt < self::MAX_COLLISIONS; ++$attempt) {
            $candidate = $this->mintToken();
            if (null === $this->storage->find($candidate)) {
                $token = new FilterUrlToken(
                    token: $candidate,
                    resourceName: $resourceName,
                    filtersSlice: $filtersSlice,
                    createdAt: new DateTimeImmutable(),
                );
                $this->storage->save($token);

                return $token;
            }
        }

        throw new RuntimeException('Failed to mint a unique FilterUrlToken after ' . self::MAX_COLLISIONS . ' attempts.');
    }

    /**
     * Look up a token. Returns `null` for unknown tokens — the
     * caller's controller responds with 404.
     */
    public function resolve(string $token): ?FilterUrlToken
    {
        if (1 !== preg_match(FilterUrlToken::TOKEN_PATTERN, $token)) {
            return null;
        }

        return $this->storage->find($token);
    }

    private function mintToken(): string
    {
        return bin2hex(random_bytes(6));
    }
}
