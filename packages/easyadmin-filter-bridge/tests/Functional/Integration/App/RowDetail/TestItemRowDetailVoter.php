<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\RowDetail;

use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants `POLYSOURCE_TEST_ROW_DETAIL` on every TestItem except the
 * `archived` ones — per-record semantics, no user involved (the
 * test firewall runs without a token; subject-only logic keeps the
 * voter deterministic under Symfony's NullToken).
 *
 * @extends Voter<string, TestItem>
 */
final class TestItemRowDetailVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'POLYSOURCE_TEST_ROW_DETAIL' === $attribute && $subject instanceof TestItem;
    }

    /**
     * 4th parameter (`$vote`) is the optional `Vote` object Symfony 8
     * passes for richer messaging. Typed `mixed` so the override is
     * compatible across Sf 6.4 / 7.x (3-param parent signature) AND
     * Sf 8 (4-param parent with `?Vote`). We don't consume it.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, mixed $vote = null): bool
    {
        \assert($subject instanceof TestItem);

        return 'archived' !== $subject->status;
    }
}
