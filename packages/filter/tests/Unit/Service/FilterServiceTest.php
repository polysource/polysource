<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Service\FilterService;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Unit contract for FilterService.
 *
 * Mocks RequestStack + SessionInterface to verify save/load/clear
 * semantics, the xxh128 key hashing, the corrupted-payload fallback,
 * and the no-session graceful no-op.
 */
final class FilterServiceTest extends TestCase
{
    private const KEY_FOR_SCOPE_1 = 'polysource.filter.';

    private function key(string $id): string
    {
        return self::KEY_FOR_SCOPE_1 . hash('xxh128', $id);
    }

    private function makeService(SessionInterface|\Throwable|null $sessionOrException): FilterService
    {
        $stack = $this->createMock(RequestStack::class);
        if ($sessionOrException instanceof \Throwable) {
            $stack->method('getSession')->willThrowException($sessionOrException);
        } elseif (null === $sessionOrException) {
            $stack->method('getSession')->willThrowException(new SessionNotFoundException('no session'));
        } else {
            $stack->method('getSession')->willReturn($sessionOrException);
        }

        return new FilterService($stack);
    }

    public function test_save_persists_collection_payload_under_hashed_key(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $service = $this->makeService($session);

        $collection = new FilterCollection('scope-1', [
            new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']),
            new FilterCriterion('price', '>=', [50]),
        ]);

        $session->expects(self::once())
            ->method('set')
            ->with(
                $this->key('scope-1'),
                [
                    ['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']],
                    ['property' => 'price', 'operator' => '>=', 'values' => [50]],
                ],
            );

        self::assertTrue($service->save($collection));
    }

    public function test_save_returns_false_when_no_session_available(): void
    {
        $service = $this->makeService(null);
        $coll = new FilterCollection('scope-1', [new FilterCriterion('p', '=', ['v'])]);

        self::assertFalse($service->save($coll));
    }

    public function test_load_returns_collection_from_valid_payload(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')
            ->with($this->key('scope-1'))
            ->willReturn([
                ['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']],
                ['property' => 'price', 'operator' => '>=', 'values' => [50]],
            ]);
        $service = $this->makeService($session);

        $loaded = $service->load('scope-1');

        self::assertInstanceOf(FilterCollection::class, $loaded);
        self::assertSame('scope-1', $loaded->id);
        self::assertCount(2, $loaded);
        self::assertSame('createdAt', $loaded->criteria[0]->property);
        self::assertSame('between', $loaded->criteria[0]->operator);
        self::assertSame(['2026-01-01', '2026-12-31'], $loaded->criteria[0]->values);
    }

    public function test_load_returns_null_when_no_session(): void
    {
        $service = $this->makeService(null);
        self::assertNull($service->load('scope-1'));
    }

    public function test_load_returns_null_when_slot_is_missing(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn(null);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'));
    }

    public function test_load_returns_null_when_payload_is_corrupted(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn([
            ['property' => 'createdAt'], // missing operator → malformed
        ]);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'), 'corrupted payload must fall back to null, not crash');
    }

    public function test_load_returns_null_when_values_are_associative(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn([
            ['property' => 'p', 'operator' => '=', 'values' => ['key' => 'val']],
        ]);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'));
    }

    public function test_load_rejects_empty_id(): void
    {
        $service = $this->makeService($this->createMock(SessionInterface::class));
        $this->expectException(\InvalidArgumentException::class);
        $service->load('');
    }

    public function test_clear_removes_session_slot(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('remove')
            ->with($this->key('scope-1'));
        $service = $this->makeService($session);

        self::assertTrue($service->clear('scope-1'));
    }

    public function test_clear_returns_false_when_no_session(): void
    {
        $service = $this->makeService(null);
        self::assertFalse($service->clear('scope-1'));
    }

    public function test_clear_rejects_empty_id(): void
    {
        $service = $this->makeService($this->createMock(SessionInterface::class));
        $this->expectException(\InvalidArgumentException::class);
        $service->clear('');
    }

    public function test_save_load_roundtrip_preserves_collection(): void
    {
        $session = new InMemorySession();
        $service = $this->makeService($session);

        $original = new FilterCollection('scope-1', [
            new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']),
            new FilterCriterion('tags', 'in', ['a', 'b', 'c']),
            new FilterCriterion('isArchived', 'isNull'),
        ]);

        $service->save($original);
        $loaded = $service->load('scope-1');

        self::assertNotNull($loaded);
        self::assertCount(3, $loaded);
        self::assertSame('createdAt', $loaded->criteria[0]->property);
        self::assertSame('tags', $loaded->criteria[1]->property);
        self::assertSame(['a', 'b', 'c'], $loaded->criteria[1]->values);
        self::assertSame([], $loaded->criteria[2]->values);
    }

    public function test_clear_after_save_drops_payload(): void
    {
        $session = new InMemorySession();
        $service = $this->makeService($session);

        $service->save(new FilterCollection('scope-1', [new FilterCriterion('p', '=', ['v'])]));
        self::assertNotNull($service->load('scope-1'));

        $service->clear('scope-1');
        self::assertNull($service->load('scope-1'));
    }

    public function test_id_scopes_are_isolated(): void
    {
        $session = new InMemorySession();
        $service = $this->makeService($session);

        $service->save(new FilterCollection('A', [new FilterCriterion('p', '=', ['valA'])]));
        $service->save(new FilterCollection('B', [new FilterCriterion('p', '=', ['valB'])]));

        self::assertSame(['valA'], $service->load('A')->criteria[0]->values);
        self::assertSame(['valB'], $service->load('B')->criteria[0]->values);
    }
}

/**
 * Tiny in-memory session double — avoids mocking ceremony for the
 * roundtrip tests where we need actual storage.
 */
final class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): bool { return true; }
    public function getId(): string { return 'in-memory'; }
    public function setId(string $id): void {}
    public function getName(): string { return 'session'; }
    public function setName(string $name): void {}
    public function invalidate(?int $lifetime = null): bool { $this->data = []; return true; }
    public function migrate(bool $destroy = false, ?int $lifetime = null): bool { return true; }
    public function save(): void {}
    public function has(string $name): bool { return isset($this->data[$name]); }
    public function get(string $name, mixed $default = null): mixed { return $this->data[$name] ?? $default; }
    public function set(string $name, mixed $value): void { $this->data[$name] = $value; }
    public function all(): array { return $this->data; }
    public function replace(array $attributes): void { $this->data = $attributes; }
    public function remove(string $name): mixed { $v = $this->data[$name] ?? null; unset($this->data[$name]); return $v; }
    public function clear(): void { $this->data = []; }
    public function isStarted(): bool { return true; }
    public function registerBag(\Symfony\Component\HttpFoundation\Session\SessionBagInterface $bag): void {}
    public function getBag(string $name): \Symfony\Component\HttpFoundation\Session\SessionBagInterface { throw new \BadMethodCallException(); }
    public function getMetadataBag(): \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag { throw new \BadMethodCallException(); }
}
