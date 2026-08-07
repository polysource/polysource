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

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        \assert($subject instanceof TestItem);

        return 'archived' !== $subject->status;
    }
}
