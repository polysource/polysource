<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\EventListener;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\EventListener\EasyAdminAuditSubscriber;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use stdClass;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class EasyAdminAuditSubscriberTest extends TestCase
{
    public function testSubscribesToTheSixEntityLifecycleEvents(): void
    {
        $events = EasyAdminAuditSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(BeforeEntityPersistedEvent::class, $events);
        self::assertArrayHasKey(BeforeEntityUpdatedEvent::class, $events);
        self::assertArrayHasKey(BeforeEntityDeletedEvent::class, $events);
        self::assertArrayHasKey(AfterEntityPersistedEvent::class, $events);
        self::assertArrayHasKey(AfterEntityUpdatedEvent::class, $events);
        self::assertArrayHasKey(AfterEntityDeletedEvent::class, $events);
        self::assertCount(6, $events);
    }

    public function testCreateFlowCapturesChangeSetAndEmitsEntryWithCreateAction(): void
    {
        $entity = new FakeEntity(id: 42, name: 'Widget', email: 'w@acme.com');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([
            'name' => [null, 'Widget'],
            'email' => [null, 'w@acme.com'],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);

        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onPersisted(new AfterEntityPersistedEvent($entity));

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertSame('create', $entry->actionName);
        self::assertSame(FakeEntity::class, $entry->resourceName);
        self::assertSame(['42'], $entry->recordIds);
        self::assertSame(AuditOutcome::Success, $entry->outcome);
        self::assertNotNull($entry->message);
        self::assertStringContainsString("name='Widget'", $entry->message);
        self::assertStringContainsString("email='w@acme.com'", $entry->message);
        self::assertArrayHasKey('changes', $entry->context);
    }

    public function testUpdateFlowEmitsDiffSummaryAndStructuredChanges(): void
    {
        $entity = new FakeEntity(id: 7, name: 'New', email: 'new@acme.com');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([
            'name' => ['Old', 'New'],
            'email' => ['old@acme.com', 'new@acme.com'],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);

        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $entry = $logger->entries[0];
        self::assertSame('update', $entry->actionName);
        self::assertStringContainsString("name: 'Old' → 'New'", $entry->message ?? '');
        self::assertStringContainsString("email: 'old@acme.com' → 'new@acme.com'", $entry->message ?? '');

        $changes = $entry->context['changes'] ?? null;
        self::assertIsArray($changes);
        self::assertSame(['old' => 'Old', 'new' => 'New'], $changes['name']);
        self::assertSame(['old' => 'old@acme.com', 'new' => 'new@acme.com'], $changes['email']);
    }

    public function testDeleteFlowSnapshotsEntityViaDoctrineMetadata(): void
    {
        $entity = new FakeEntity(id: 99, name: 'Doomed', email: 'bye@acme.com');
        $logger = new RecordingLogger2();
        $em = $this->emWithSnapshot(['id' => 99, 'name' => 'Doomed', 'email' => 'bye@acme.com']);

        $subscriber = $this->makeSubscriber($logger, $em);

        $subscriber->onBeforeDeleted(new BeforeEntityDeletedEvent($entity));
        $subscriber->onDeleted(new AfterEntityDeletedEvent($entity));

        $entry = $logger->entries[0];
        self::assertSame('delete', $entry->actionName);
        self::assertSame(['99'], $entry->recordIds);
        self::assertStringContainsString('name=', $entry->message ?? '');
        self::assertArrayHasKey('snapshot', $entry->context);
        self::assertSame('Doomed', $entry->context['snapshot']['name']);
    }

    public function testExtractIdentifierPrefersGetIdMethodOverGetUuidOrPublicProperty(): void
    {
        $entity = new FakeEntity(id: 42, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onPersisted(new AfterEntityPersistedEvent($entity));

        self::assertSame(['42'], $logger->entries[0]->recordIds);
    }

    public function testExtractIdentifierFallsBackToGetUuidWhenGetIdAbsent(): void
    {
        $entity = new EntityWithUuid('019df-uuid-7');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onPersisted(new AfterEntityPersistedEvent($entity));

        self::assertSame(['019df-uuid-7'], $logger->entries[0]->recordIds);
    }

    public function testExtractIdentifierFallsBackToPublicIdProperty(): void
    {
        $entity = new EntityWithPublicId();
        $entity->id = 'pub-123';
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onPersisted(new AfterEntityPersistedEvent($entity));

        self::assertSame(['pub-123'], $logger->entries[0]->recordIds);
    }

    public function testExtractIdentifierReturnsEmptyListWhenNoIdentifierMethodOrPropertyExists(): void
    {
        $entity = new EntityWithoutId();
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onPersisted(new AfterEntityPersistedEvent($entity));

        self::assertSame([], $logger->entries[0]->recordIds);
    }

    public function testDateTimeChangesAreSerialisedAsAtomStrings(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $past = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $now = new DateTimeImmutable('2026-05-09T10:00:00+00:00');
        $em = $this->emWithChangeSet([
            'updatedAt' => [$past, $now],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $changes = $logger->entries[0]->context['changes'];
        self::assertSame('2026-01-01T10:00:00+00:00', $changes['updatedAt']['old']);
        self::assertSame('2026-05-09T10:00:00+00:00', $changes['updatedAt']['new']);
    }

    public function testStringableObjectsAreCoercedToTheirStringRepresentation(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'StringableValue';
            }
        };
        $em = $this->emWithChangeSet([
            'related' => [null, $stringable],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame('StringableValue', $logger->entries[0]->context['changes']['related']['new']);
    }

    public function testNonStringableObjectsAreRecordedAsClassNamePlaceholder(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $obj = new stdClass();
        $em = $this->emWithChangeSet([
            'opaque' => [null, $obj],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame('[object stdClass]', $logger->entries[0]->context['changes']['opaque']['new']);
    }

    public function testArrayValuesAreRecursivelySerialised(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([
            'tags' => [['old1'], ['new1', 'new2']],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame(['old1'], $logger->entries[0]->context['changes']['tags']['old']);
        self::assertSame(['new1', 'new2'], $logger->entries[0]->context['changes']['tags']['new']);
    }

    public function testMessageIsTruncatedWhenItExceedsMaxBytes(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $hugeOld = str_repeat('a', 600);
        $hugeNew = str_repeat('b', 600);
        $em = $this->emWithChangeSet([
            'huge' => [$hugeOld, $hugeNew],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $message = $logger->entries[0]->message ?? '';
        self::assertLessThanOrEqual(
            EasyAdminAuditSubscriber::MAX_MESSAGE_BYTES + 20, // + truncation marker
            \strlen($message),
        );
        self::assertStringContainsString('truncated', $message);
    }

    public function testActorIdentityComesFromAuditActor(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber(
            $logger,
            $em,
            actor: new FixedActor2('alice@acme.com', 'Alice Smith'),
        );

        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $entry = $logger->entries[0];
        self::assertSame('alice@acme.com', $entry->actorId);
        self::assertSame('Alice Smith', $entry->actorLabel);
    }

    public function testContextIncludesIpAndRequestIdFromCurrentRequest(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $request = Request::create('/admin/order/1/edit');
        $request->server->set('REMOTE_ADDR', '203.0.113.5');
        $request->headers->set('X-Request-Id', 'req-abc-123');

        $stack = new RequestStack();
        $stack->push($request);

        $subscriber = $this->makeSubscriber($logger, $em, requestStack: $stack);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $context = $logger->entries[0]->context;
        self::assertSame('203.0.113.5', $context['ip']);
        self::assertSame('req-abc-123', $context['requestId']);
    }

    public function testMissingRequestStackProducesEmptyContext(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $subscriber = $this->makeSubscriber($logger, $em); // empty RequestStack

        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $context = $logger->entries[0]->context;
        self::assertArrayNotHasKey('ip', $context);
        self::assertArrayNotHasKey('userAgent', $context);
        self::assertArrayNotHasKey('requestId', $context);
    }

    public function testMissingXRequestIdHeaderGeneratesRandomRequestId(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([]);

        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $subscriber = $this->makeSubscriber($logger, $em, requestStack: $stack);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $context = $logger->entries[0]->context;
        self::assertNotEmpty($context['requestId']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $context['requestId']);
    }

    public function testFormatScalarHandlesNullBoolNumericAndStringConsistently(): void
    {
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet([
            'a' => [null, 'value'],
            'b' => [true, false],
            'c' => [42, 99],
            'd' => [1.5, 2.5],
        ]);

        $subscriber = $this->makeSubscriber($logger, $em);
        $subscriber->onBeforeUpdated(new BeforeEntityUpdatedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $message = $logger->entries[0]->message ?? '';
        self::assertStringContainsString("a: null → 'value'", $message);
        self::assertStringContainsString('b: true → false', $message);
        self::assertStringContainsString('c: 42 → 99', $message);
        self::assertStringContainsString('d: 1.5 → 2.5', $message);
    }

    public function testActionMismatchBetweenBeforeAndAfterDropsCapturedDiff(): void
    {
        // Defensive guard: if EA dispatches a different After*Event than
        // the captured Before*Event for the same entity (would only
        // happen on a EA bug), the subscriber falls back to "no diff"
        // rather than emitting wrong data.
        $entity = new FakeEntity(id: 1, name: 'X', email: 'x@a.co');
        $logger = new RecordingLogger2();
        $em = $this->emWithChangeSet(['name' => ['Old', 'New']]);

        $subscriber = $this->makeSubscriber($logger, $em);

        // Capture as "create" but emit as "update" — wrong action.
        $subscriber->onBeforePersisted(new BeforeEntityPersistedEvent($entity));
        $subscriber->onUpdated(new AfterEntityUpdatedEvent($entity));

        $entry = $logger->entries[0];
        self::assertSame('update', $entry->actionName);
        self::assertNull($entry->message, 'mismatch must drop the captured diff to avoid mislogging');
        self::assertArrayNotHasKey('changes', $entry->context);
    }

    private function makeSubscriber(
        AuditLoggerInterface $logger,
        EntityManagerInterface $em,
        ?AuditActorInterface $actor = null,
        ?RequestStack $requestStack = null,
    ): EasyAdminAuditSubscriber {
        return new EasyAdminAuditSubscriber(
            logger: $logger,
            actor: $actor ?? new FixedActor2('test@x.co', 'Test'),
            requestStack: $requestStack ?? new RequestStack(),
            em: $em,
        );
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function emWithChangeSet(array $changeSet): EntityManagerInterface
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('computeChangeSets');
        $uow->method('getEntityChangeSet')->willReturn($changeSet);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        return $em;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function emWithSnapshot(array $snapshot): EntityManagerInterface
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn(array_keys($snapshot));
        $metadata->method('getFieldValue')->willReturnCallback(
            static fn (object $entity, string $field): mixed => $snapshot[$field] ?? null,
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        return $em;
    }
}

final class FakeEntity
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class EntityWithUuid
{
    public function __construct(private readonly string $uuid)
    {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}

final class EntityWithPublicId
{
    public string $id = '';
}

final class EntityWithoutId
{
}

final class FixedActor2 implements AuditActorInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $label,
    ) {
    }

    public function getActorId(): string
    {
        return $this->id;
    }

    public function getActorLabel(): ?string
    {
        return $this->label;
    }
}

final class RecordingLogger2 implements AuditLoggerInterface
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function log(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}
