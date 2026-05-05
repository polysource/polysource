<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Service;

use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Service\FilterService;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

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

    private function makeService(SessionInterface|Throwable|null $sessionOrException): FilterService
    {
        $stack = $this->createMock(RequestStack::class);
        if ($sessionOrException instanceof Throwable) {
            $stack->method('getSession')->willThrowException($sessionOrException);
        } elseif (null === $sessionOrException) {
            $stack->method('getSession')->willThrowException(new SessionNotFoundException('no session'));
        } else {
            $stack->method('getSession')->willReturn($sessionOrException);
        }

        return new FilterService($stack);
    }

    public function testSavePersistsCollectionPayloadUnderHashedKey(): void
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

    public function testSaveReturnsFalseWhenNoSessionAvailable(): void
    {
        $service = $this->makeService(null);
        $coll = new FilterCollection('scope-1', [new FilterCriterion('p', '=', ['v'])]);

        self::assertFalse($service->save($coll));
    }

    public function testLoadReturnsCollectionFromValidPayload(): void
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

    public function testLoadReturnsNullWhenNoSession(): void
    {
        $service = $this->makeService(null);
        self::assertNull($service->load('scope-1'));
    }

    public function testLoadReturnsNullWhenSlotIsMissing(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn(null);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'));
    }

    public function testLoadReturnsNullWhenPayloadIsCorrupted(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn([
            ['property' => 'createdAt'], // missing operator → malformed
        ]);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'), 'corrupted payload must fall back to null, not crash');
    }

    public function testLoadReturnsNullWhenValuesAreAssociative(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn([
            ['property' => 'p', 'operator' => '=', 'values' => ['key' => 'val']],
        ]);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'));
    }

    public function testLoadReturnsNullWhenValuesKeyIsMissing(): void
    {
        // Stricter than the previous "?? []" fallback: a payload that
        // doesn't even carry the `values` key is treated as a schema
        // mismatch (older bridge / hand-edited session), not silently
        // coerced to an empty-values criterion.
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn([
            ['property' => 'p', 'operator' => '='], // no `values` key
        ]);
        $service = $this->makeService($session);

        self::assertNull($service->load('scope-1'));
    }

    public function testLoadRejectsEmptyId(): void
    {
        $service = $this->makeService($this->createMock(SessionInterface::class));
        $this->expectException(InvalidArgumentException::class);
        $service->load('');
    }

    public function testClearRemovesSessionSlot(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('remove')
            ->with($this->key('scope-1'));
        $service = $this->makeService($session);

        self::assertTrue($service->clear('scope-1'));
    }

    public function testClearReturnsFalseWhenNoSession(): void
    {
        $service = $this->makeService(null);
        self::assertFalse($service->clear('scope-1'));
    }

    public function testClearRejectsEmptyId(): void
    {
        $service = $this->makeService($this->createMock(SessionInterface::class));
        $this->expectException(InvalidArgumentException::class);
        $service->clear('');
    }

    public function testSaveLoadRoundtripPreservesCollection(): void
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

    /* ---------- buildUrl() — URL-shareable filter state -------- */

    public function testBuildUrlReturnsBarePathForEmptyCollection(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('users.list');

        self::assertSame('/admin/users', $service->buildUrl('/admin/users', $collection));
    }

    /**
     * Helper — narrows `parse_url()` + `parse_str()` for PHPStan.
     *
     * `parse_url()` returns `false` only on truly malformed URIs;
     * the test inputs here are always well-formed. We assert the
     * array shape so PHPStan sees the offset accesses as safe.
     *
     * Return shape is `array<int|string, array<mixed>|string>` —
     * the natural type of `parse_str()`'s output. Tests cast or
     * narrow at the call site when they need a specific shape.
     *
     * @return array<int|string, array<mixed>|string>
     */
    private function parseQuery(string $url): array
    {
        $parts = parse_url($url);
        self::assertIsArray($parts);
        $rawQuery = $parts['query'] ?? '';
        self::assertIsString($rawQuery);
        $parsed = [];
        parse_str($rawQuery, $parsed);

        return $parsed;
    }

    public function testBuildUrlEncodesSingleCriterionInDefaultFormName(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('users.list', [
            new FilterCriterion('status', '=', ['active']),
        ]);

        $url = $service->buildUrl('/admin/users', $collection);

        $parts = parse_url($url);
        self::assertIsArray($parts);
        self::assertSame('/admin/users', $parts['path'] ?? '');
        self::assertSame(
            ['filter' => ['status' => ['operator' => '=', 'values' => ['active']]]],
            $this->parseQuery($url),
        );
    }

    public function testBuildUrlEncodesBetweenWithMultipleValues(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('orders.list', [
            new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']),
        ]);

        $url = $service->buildUrl('/admin/orders', $collection);

        self::assertSame(
            ['filter' => ['createdAt' => ['operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']]]],
            $this->parseQuery($url),
        );
    }

    public function testBuildUrlEncodesMultipleCriteriaPreservingOrder(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('orders.list', [
            new FilterCriterion('status', '=', ['paid']),
            new FilterCriterion('total', '>=', [100]),
        ]);

        $url = $service->buildUrl('/admin/orders', $collection);

        // `parse_str()` returns strings (query strings are stringly-typed
        // by the HTTP spec). The integer `100` round-trips as the string
        // `'100'` — hosts that need typed values cast inside the mapper.
        self::assertSame(
            [
                'filter' => [
                    'status' => ['operator' => '=', 'values' => ['paid']],
                    'total' => ['operator' => '>=', 'values' => ['100']],
                ],
            ],
            $this->parseQuery($url),
        );
    }

    public function testBuildUrlMergesExtraQueryParams(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('users.list', [
            new FilterCriterion('status', '=', ['active']),
        ]);

        $url = $service->buildUrl('/admin/users', $collection, ['page' => 2, 'sort' => 'createdAt:desc']);

        $parsed = $this->parseQuery($url);
        self::assertSame('2', $parsed['page']);
        self::assertSame('createdAt:desc', $parsed['sort']);
        self::assertSame(['status' => ['operator' => '=', 'values' => ['active']]], $parsed['filter']);
    }

    public function testBuildUrlAcceptsCustomFormName(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('users.list', [
            new FilterCriterion('status', '=', ['active']),
        ]);

        $url = $service->buildUrl('/admin/users', $collection, formName: 'filters');

        $parsed = $this->parseQuery($url);
        self::assertArrayNotHasKey('filter', $parsed);
        self::assertSame(['status' => ['operator' => '=', 'values' => ['active']]], $parsed['filters']);
    }

    public function testBuildUrlEscapesSpecialCharacters(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('search', [
            new FilterCriterion('q', 'like', ['hello world & %wild%']),
        ]);

        $url = $service->buildUrl('/admin/search', $collection);

        // Round-trip through parse_str — special chars must survive.
        $parsed = $this->parseQuery($url);
        self::assertIsArray($parsed['filter']);
        self::assertIsArray($parsed['filter']['q']);
        self::assertIsArray($parsed['filter']['q']['values']);
        self::assertSame('hello world & %wild%', $parsed['filter']['q']['values'][0]);
    }

    public function testBuildUrlPreservesExistingQueryStringOnPath(): void
    {
        $service = $this->makeService(null);
        $collection = new FilterCollection('users.list', [
            new FilterCriterion('status', '=', ['active']),
        ]);

        // A path that already contains a query string — `?page=1` was set by the
        // caller (e.g. UrlGeneratorInterface::generate() with route defaults).
        // buildUrl() must merge with it rather than overwriting.
        $url = $service->buildUrl('/admin/users?page=1', $collection);

        $parts = parse_url($url);
        self::assertIsArray($parts);
        self::assertSame('/admin/users', $parts['path'] ?? '');
        $parsed = $this->parseQuery($url);
        self::assertSame('1', $parsed['page']);
        self::assertArrayHasKey('filter', $parsed);
    }

    public function testBuildUrlRejectsEmptyPath(): void
    {
        $service = $this->makeService(null);

        $this->expectException(InvalidArgumentException::class);
        $service->buildUrl('', new FilterCollection('users.list'));
    }

    public function testBuildUrlRejectsEmptyFormName(): void
    {
        $service = $this->makeService(null);

        $this->expectException(InvalidArgumentException::class);
        $service->buildUrl('/admin/users', new FilterCollection('users.list'), formName: '');
    }

    public function testClearAfterSaveDropsPayload(): void
    {
        $session = new InMemorySession();
        $service = $this->makeService($session);

        $service->save(new FilterCollection('scope-1', [new FilterCriterion('p', '=', ['v'])]));
        self::assertNotNull($service->load('scope-1'));

        $service->clear('scope-1');
        self::assertNull($service->load('scope-1'));
    }

    public function testIdScopesAreIsolated(): void
    {
        $session = new InMemorySession();
        $service = $this->makeService($session);

        $service->save(new FilterCollection('A', [new FilterCriterion('p', '=', ['valA'])]));
        $service->save(new FilterCollection('B', [new FilterCriterion('p', '=', ['valB'])]));

        $loadedA = $service->load('A');
        $loadedB = $service->load('B');
        self::assertNotNull($loadedA);
        self::assertNotNull($loadedB);
        self::assertSame(['valA'], $loadedA->criteria[0]->values);
        self::assertSame(['valB'], $loadedB->criteria[0]->values);
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

    public function start(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'in-memory';
    }

    public function setId(string $id): void
    {
    }

    public function getName(): string
    {
        return 'session';
    }

    public function setName(string $name): void
    {
    }

    public function invalidate(?int $lifetime = null): bool
    {
        $this->data = [];

        return true;
    }

    public function migrate(bool $destroy = false, ?int $lifetime = null): bool
    {
        return true;
    }

    public function save(): void
    {
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @phpstan-ignore-next-line missingType.iterableValue — LSP requires bare `array` to match SessionInterface::replace() */
    public function replace(array $attributes): void
    {
        /** @var array<string, mixed> $attributes */
        $this->data = $attributes;
    }

    public function remove(string $name): mixed
    {
        $v = $this->data[$name] ?? null;
        unset($this->data[$name]);

        return $v;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function registerBag(\Symfony\Component\HttpFoundation\Session\SessionBagInterface $bag): void
    {
    }

    public function getBag(string $name): \Symfony\Component\HttpFoundation\Session\SessionBagInterface
    {
        throw new BadMethodCallException();
    }

    public function getMetadataBag(): \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag
    {
        throw new BadMethodCallException();
    }
}
