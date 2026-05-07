<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Fixture;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * In-memory CSRF token storage for the functional test kernel.
 *
 * Symfony's default `SessionTokenStorage` requires an active request /
 * session, which is awkward to set up around `CsrfTokenManager::getToken()`
 * called outside the request lifecycle. This fixture sidesteps the issue
 * for tests — production code keeps the session-backed default.
 */
final class InMemoryCsrfTokenStorage implements TokenStorageInterface
{
    /** @var array<string, string> */
    private array $tokens = [];

    public function getToken(string $tokenId): string
    {
        if (!isset($this->tokens[$tokenId])) {
            throw new TokenNotFoundException();
        }

        return $this->tokens[$tokenId];
    }

    public function setToken(string $tokenId, string $token): void
    {
        $this->tokens[$tokenId] = $token;
    }

    public function removeToken(string $tokenId): ?string
    {
        $token = $this->tokens[$tokenId] ?? null;
        unset($this->tokens[$tokenId]);

        return $token;
    }

    public function hasToken(string $tokenId): bool
    {
        return isset($this->tokens[$tokenId]);
    }
}
