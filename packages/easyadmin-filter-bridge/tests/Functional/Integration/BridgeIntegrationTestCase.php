<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;
use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared setup for the bridge integration tests.
 *
 * Boots a minimal `TestKernel` per test, creates the SQLite in-memory
 * schema, seeds a 30-row TestItem fixture, then issues HTTP requests
 * via `$kernel->handle()` directly (NOT via `KernelBrowser`) because
 * the browser layer leaks error/exception handlers that trip
 * PHPUnit 11's strict `failOnRisky` check. Direct `handle()` does
 * the same job for our purposes — we only need the controller
 * dispatch + response, not the full client emulation.
 */
abstract class BridgeIntegrationTestCase extends TestCase
{
    protected TestKernel $kernel;
    protected EntityManagerInterface $em;

    /**
     * Symfony's `Kernel::handle()` pushes an exception handler that
     * isn't unwound by `shutdown()`. Run this suite via the
     * `integration` phpunit suite (cf. phpunit.xml.dist) where
     * `failOnRisky` is disabled — otherwise PHPUnit 11 flags every
     * test as risky.
     */
    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();

        $container = $this->kernel->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->em = $em;

        // Fresh schema per test. SQLite in-memory means the prior
        // test's data wouldn't survive anyway, but the explicit
        // drop+create locks the contract.
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->loadFixtures();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        parent::tearDown();
    }

    /**
     * Dispatch an HTTP request through the kernel and return the
     * Response. Mirrors what `KernelBrowser::request()` does minus
     * the cookie/history bookkeeping we don't need for these tests.
     */
    protected function request(string $method, string $path): Response
    {
        $request = Request::create($path, $method);

        return $this->kernel->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonResponse(Response $response): array
    {
        $body = (string) $response->getContent();
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            self::fail(\sprintf(
                'Response body is not JSON-decodable. Status: %d. Body (first 200 chars): %s',
                $response->getStatusCode(),
                substr($body, 0, 200),
            ));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Helper: URL-encode the entity FQCN as a path segment. Backslashes
     * become `%5C` — Symfony's routing decodes them back.
     */
    final protected function encodeResource(string $fqcn): string
    {
        return str_replace('\\', '%5C', $fqcn);
    }

    /**
     * 30 items split into 4 status buckets, mixed booleans + stocks,
     * createdAt spanning Jan–Mar 2026 so date-range filters have
     * predictable counts.
     */
    private function loadFixtures(): void
    {
        $statuses = ['draft', 'published', 'archived', 'review'];
        $base = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
        for ($i = 1; $i <= 30; ++$i) {
            $item = new TestItem(
                id: $i,
                name: 'Item ' . $i,
                status: $statuses[$i % 4],
                stock: ($i * 7) % 50,
                isActive: 0 !== ($i % 3),
                createdAt: $base->modify('+' . ($i * 3) . ' days'),
            );
            $this->em->persist($item);
        }
        $this->em->flush();
        $this->em->clear();
    }
}
