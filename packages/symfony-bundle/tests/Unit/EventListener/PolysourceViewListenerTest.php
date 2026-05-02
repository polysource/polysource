<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\EventListener;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\EventListener\PolysourceViewListener;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataRecord;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PolysourceViewListener::class)]
final class PolysourceViewListenerTest extends TestCase
{
    #[Test]
    public function ignoresNonPolysourceControllerResults(): void
    {
        $listener = new PolysourceViewListener();
        $event = $this->buildEvent('hello');

        ($listener)($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function fallsBackToJsonWhenTwigIsAbsent(): void
    {
        $listener = new PolysourceViewListener();
        $resource = new FakeResource('flags', label: 'Feature flags');
        $page = new DataPage(
            items: [new DataRecord('1', ['name' => 'flag-a'])],
            total: 1,
        );

        $view = new PolysourceView('@Polysource/index.html.twig', [
            'resource' => $resource,
            'page' => $page,
        ]);
        $event = $this->buildEvent($view);

        ($listener)($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $payload = self::decode($response);
        self::assertSame('@Polysource/index.html.twig', $payload['template'] ?? null);
        $resource = $payload['resource'] ?? null;
        self::assertIsArray($resource);
        self::assertSame('flags', $resource['name'] ?? null);
        self::assertSame('Feature flags', $resource['label'] ?? null);
        $page = $payload['page'] ?? null;
        self::assertIsArray($page);
        self::assertSame(1, $page['total'] ?? null);
        $items = $page['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);
        $first = $items[0] ?? null;
        self::assertIsArray($first);
        self::assertSame('1', $first['id'] ?? null);
        $properties = $first['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertSame('flag-a', $properties['name'] ?? null);
    }

    #[Test]
    public function serialisesSingleRecord(): void
    {
        $listener = new PolysourceViewListener();
        $view = new PolysourceView('@Polysource/detail.html.twig', [
            'resource' => new FakeResource('flags'),
            'record' => new DataRecord('42', ['name' => 'flag-b']),
        ]);
        $event = $this->buildEvent($view);

        ($listener)($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        $payload = self::decode($response);
        $record = $payload['record'] ?? null;
        self::assertIsArray($record);
        self::assertSame('42', $record['id'] ?? null);
        $properties = $record['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertSame('flag-b', $properties['name'] ?? null);
    }

    #[Test]
    public function honorsCustomStatusCode(): void
    {
        $listener = new PolysourceViewListener();
        $view = new PolysourceView('@Polysource/error.html.twig', [], statusCode: 422);
        $event = $this->buildEvent($view);

        ($listener)($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(JsonResponse $response): array
    {
        $body = $response->getContent();
        self::assertIsString($body);
        $payload = json_decode($body, true);
        self::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function buildEvent(mixed $controllerResult): ViewEvent
    {
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response
            {
                throw new LogicException('not used');
            }
        };

        return new ViewEvent(
            $kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult,
        );
    }
}
