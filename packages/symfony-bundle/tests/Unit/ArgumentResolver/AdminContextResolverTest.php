<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\ArgumentResolver;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\ArgumentResolver\AdminContextResolver;
use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Context\AdminContextProvider;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Core\Query\SortDirection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[CoversClass(AdminContextResolver::class)]
final class AdminContextResolverTest extends TestCase
{
    #[Test]
    public function yieldsNothingWhenArgumentTypeMismatches(): void
    {
        $resolver = $this->buildResolver([new FakeResource('flags')]);
        $request = $this->buildRequest('flags', 'polysource_index');

        $result = $resolver->resolve($request, new ArgumentMetadata('foo', 'string', false, false, null));

        self::assertSame([], iterator_to_array($this->toIterable($result)));
    }

    #[Test]
    public function yieldsNothingWhenResourceNameAttributeMissing(): void
    {
        $resolver = $this->buildResolver([new FakeResource('flags')]);
        $request = new Request();

        $result = $resolver->resolve($request, $this->adminContextArgument());

        self::assertSame([], iterator_to_array($this->toIterable($result)));
    }

    #[Test]
    public function buildsContextWithIndexActionFromRouteDefault(): void
    {
        $resource = new FakeResource('flags');
        $resolver = $this->buildResolver([$resource]);
        $request = $this->buildRequest('flags', 'polysource_flags_index', [
            '_polysource_action' => 'index',
        ]);

        $contexts = iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ));

        self::assertCount(1, $contexts);
        $context = $contexts[0];
        self::assertInstanceOf(AdminContext::class, $context);
        self::assertSame($resource, $context->resource);
        self::assertSame('index', $context->action);
        self::assertNull($context->recordId);
    }

    #[Test]
    public function buildsContextWithDetailActionAndRecordId(): void
    {
        $resolver = $this->buildResolver([new FakeResource('flags')]);
        $request = $this->buildRequest('flags', 'polysource_flags_detail', [
            'id' => '42',
            '_polysource_action' => 'detail',
        ]);

        $context = iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ))[0];
        \assert($context instanceof AdminContext);

        self::assertSame('detail', $context->action);
        self::assertSame('42', $context->recordId);
    }

    #[Test]
    public function buildsContextUsingExplicitActionAttribute(): void
    {
        $resolver = $this->buildResolver([new FakeResource('flags')]);
        $request = $this->buildRequest('flags', 'polysource_flags_action', [
            'id' => '7',
            'action' => 'retry',
        ]);

        $context = iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ))[0];
        \assert($context instanceof AdminContext);

        self::assertSame('retry', $context->action);
        self::assertSame('7', $context->recordId);
    }

    #[Test]
    public function buildsQueryFromRequestParameters(): void
    {
        $resolver = $this->buildResolver([new FakeResource('flags')]);
        $request = $this->buildRequest('flags', 'polysource_flags_index', [
            '_polysource_action' => 'index',
        ], queryParams: [
            'q' => 'hello',
            'filter' => ['status' => 'active'],
            'sort' => ['createdAt' => 'desc'],
            'page' => '3',
            'pageSize' => '10',
        ]);

        $context = iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ))[0];
        \assert($context instanceof AdminContext);

        self::assertSame('hello', $context->query->searchText);
        self::assertArrayHasKey('status', $context->query->filters);
        self::assertSame('active', $context->query->filters['status']->value);
        self::assertArrayHasKey('createdAt', $context->query->sort);
        self::assertSame(SortDirection::DESC, $context->query->sort['createdAt']);
        self::assertNotNull($context->query->pagination);
        self::assertSame(20, $context->query->pagination->offset);
        self::assertSame(10, $context->query->pagination->limit);
    }

    #[Test]
    public function clampsPageSizeToConfiguredMaximum(): void
    {
        $resolver = new AdminContextResolver(
            new ResourceRegistry([new FakeResource('flags')]),
            new AdminContextProvider(),
            null,
            maxPageSize: 50,
        );
        $request = $this->buildRequest('flags', 'polysource_flags_index', [
            '_polysource_action' => 'index',
        ], queryParams: [
            'pageSize' => '999999',
        ]);

        $context = iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ))[0];
        \assert($context instanceof AdminContext);

        self::assertNotNull($context->query->pagination);
        self::assertSame(50, $context->query->pagination->limit);
    }

    #[Test]
    public function persistsContextInProvider(): void
    {
        $provider = new AdminContextProvider();
        $resolver = new AdminContextResolver(
            new ResourceRegistry([new FakeResource('flags')]),
            $provider,
        );

        $request = $this->buildRequest('flags', 'polysource_flags_index', [
            '_polysource_action' => 'index',
        ]);
        iterator_to_array($this->toIterable(
            $resolver->resolve($request, $this->adminContextArgument()),
        ));

        $context = $provider->getContext();
        self::assertNotNull($context);
        self::assertSame('flags', $context->resource->getName());
    }

    /**
     * @param iterable<int, FakeResource> $resources
     */
    private function buildResolver(iterable $resources): AdminContextResolver
    {
        return new AdminContextResolver(
            new ResourceRegistry($resources),
            new AdminContextProvider(),
        );
    }

    /**
     * @param array<string, scalar> $attributes
     * @param array<string, mixed>  $queryParams
     */
    private function buildRequest(
        string $resourceName,
        string $route,
        array $attributes = [],
        array $queryParams = [],
    ): Request {
        $request = new Request($queryParams);
        $request->attributes->set('_route', $route);
        $request->attributes->set('resourceName', $resourceName);
        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }

    private function adminContextArgument(): ArgumentMetadata
    {
        return new ArgumentMetadata('context', AdminContext::class, false, false, null);
    }

    /**
     * Bridges any iterable to a concrete Generator so PHPStan
     * (with phpVersion=8.1 per ADR-015) is happy with the
     * `iterator_to_array()` calls below — that signature requires
     * Traversable on PHP 8.1, was widened to iterable in 8.2.
     * Reindexes to int keys so the Generator's key type is
     * concretely `int` (Generator forbids `mixed` keys).
     *
     * @param iterable<mixed> $iterable
     *
     * @return Generator<int, mixed>
     */
    private function toIterable(iterable $iterable): Generator
    {
        $i = 0;
        foreach ($iterable as $value) {
            yield $i++ => $value;
        }
    }
}
