<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Storage;

use DateTimeImmutable;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;

/**
 * In-memory storage — primarily for unit tests.
 *
 * @since 0.5.0
 */
final class InMemoryFilterUrlTokenStorage implements FilterUrlTokenStorageInterface
{
    /** @var array<string, FilterUrlToken> */
    private array $byToken = [];

    public function save(FilterUrlToken $token): void
    {
        $this->byToken[$token->token] = $token;
    }

    public function find(string $token): ?FilterUrlToken
    {
        return $this->byToken[$token] ?? null;
    }

    public function purgeOlderThan(DateTimeImmutable $cutoff): int
    {
        $removed = 0;
        foreach ($this->byToken as $key => $entry) {
            if ($entry->createdAt < $cutoff) {
                unset($this->byToken[$key]);
                ++$removed;
            }
        }

        return $removed;
    }
}
