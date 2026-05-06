<?php

declare(strict_types=1);

namespace App\Story;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * 50 audit entries spread across the past 30 days. Distribution
 * mirrors a real ops day — mostly successful retries, a few
 * failures, an occasional exception. Demonstrates the
 * polysource/audit table populated for the demo + makes the
 * "Export CSV" GDPR Art. 30 action have actual rows to export.
 *
 * Writes directly via Doctrine — Foundry has no native factory for
 * the polysource AuditEntryRecord (the upstream package treats it
 * as host-internal storage), so we hand-craft the ORM rows here.
 */
final class AuditEntriesStory extends Story
{
    private const RESOURCES = [
        'failed-messages' => ['retry', 'dismiss', 'retry-all', 'purge'],
        'bulk-jobs' => ['cancel'],
        'login-attempts' => ['view'],
        'cache-keys' => ['delete', 'view'],
        's3-files' => ['view', 'delete'],
    ];

    private const ACTORS = [
        ['admin@shop.co', 'Alice Anderson'],
        ['ops@shop.co', 'Olivier Operator'],
        ['ops-jane@shop.co', 'Jane Doe'],
        ['ops-marcus@shop.co', 'Marcus Lee'],
    ];

    private const OUTCOMES = ['success', 'success', 'success', 'success', 'failure', 'exception'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function build(): void
    {
        for ($i = 0; $i < 50; ++$i) {
            $resource = (string) array_rand(self::RESOURCES);
            $action = self::RESOURCES[$resource][array_rand(self::RESOURCES[$resource])];
            [$actorId, $actorLabel] = self::ACTORS[array_rand(self::ACTORS)];
            $outcome = self::OUTCOMES[array_rand(self::OUTCOMES)];

            $occurredAt = new \DateTimeImmutable(sprintf('-%d hours -%d minutes', random_int(0, 720), random_int(0, 59)));

            $record = new AuditEntryRecord();
            $record->id = Uuid::v7()->toRfc4122();
            $record->occurredAt = $occurredAt;
            $record->actorId = $actorId;
            $record->actorLabel = $actorLabel;
            $record->resourceName = $resource;
            $record->actionName = $action;
            $record->recordIdsJson = json_encode([
                Uuid::v7()->toRfc4122(),
            ], \JSON_THROW_ON_ERROR);
            $record->outcome = $outcome;
            $record->message = match ($outcome) {
                'failure' => 'Operation completed with errors',
                'exception' => 'Uncaught exception during execution',
                default => null,
            };
            $record->durationMs = random_int(8, 1200);
            $record->contextJson = json_encode([
                'request_id' => 'req-'.bin2hex(random_bytes(8)),
                'ip' => sprintf('192.0.2.%d', random_int(1, 254)),
            ], \JSON_THROW_ON_ERROR);

            $this->em->persist($record);
        }

        $this->em->flush();
    }
}
