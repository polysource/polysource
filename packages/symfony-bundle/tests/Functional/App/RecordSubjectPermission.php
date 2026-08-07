<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Permission\PermissionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Permission fixture behaving like a per-record Symfony voter:
 * `RECORD_OWNER` is granted only when the subject is a
 * {@see DataRecord} whose `owner` property is `me`; every other
 * attribute is granted unconditionally (resource view, other
 * actions, …).
 */
final class RecordSubjectPermission implements PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        if ('RECORD_OWNER' !== $attribute) {
            return true;
        }

        return $subject instanceof DataRecord && 'me' === $subject->get('owner');
    }
}
