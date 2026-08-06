<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Controller\FilterUrlTokenController;
use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Polysource\Filter\FilterUrlToken\Storage\InMemoryFilterUrlTokenStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Behavioural tests for {@see FilterUrlTokenController}.
 *
 * Uses the REAL {@see FilterUrlTokenService} on the in-memory
 * storage — tokens are minted through the same code path production
 * uses, then resolved through the controller.
 */
#[CoversClass(FilterUrlTokenController::class)]
final class FilterUrlTokenControllerTest extends TestCase
{
    private const RESOURCE = 'App\Entity\Order';

    private FilterUrlTokenService $service;
    private FilterUrlTokenController $controller;

    protected function setUp(): void
    {
        $this->service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());
        $this->controller = new FilterUrlTokenController($this->service);
    }

    /**
     * @param array<string, mixed> $slice
     */
    private function mint(array $slice = ['status' => ['value' => 'paid', 'comparison' => '=']]): string
    {
        $token = $this->service->tokenize(self::RESOURCE, $slice);
        self::assertNotNull($token);

        return $token->token;
    }

    #[Test]
    public function unknownTokenIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller->resolve(
            Request::create('/admin/polysource/f/aaaaaaaaaaaa?index=/admin'),
            'aaaaaaaaaaaa',
        );
    }

    #[Test]
    public function missingIndexTargetIs404(): void
    {
        $token = $this->mint();

        $this->expectException(NotFoundHttpException::class);

        $this->controller->resolve(
            Request::create('/admin/polysource/f/' . $token),
            $token,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonSameOriginIndexTargets(): iterable
    {
        yield 'absolute URL' => ['https://evil.example/admin'];
        yield 'no leading slash' => ['admin/orders'];
        // `//host` is a protocol-RELATIVE URL — redirecting to it
        // leaves the origin (open redirect). Regression coverage for
        // the 2026-08-06 hardening.
        yield 'protocol-relative' => ['//evil.example/admin'];
        // Browsers normalise `/\` to `//`.
        yield 'backslash protocol-relative' => ['/\\evil.example/admin'];
    }

    #[Test]
    #[DataProvider('nonSameOriginIndexTargets')]
    public function nonSameOriginIndexTargetIs404(string $index): void
    {
        $token = $this->mint();

        $this->expectException(NotFoundHttpException::class);

        $this->controller->resolve(
            Request::create('/admin/polysource/f/' . $token . '?index=' . rawurlencode($index)),
            $token,
        );
    }

    #[Test]
    public function redirectsToIndexWithTheStoredFiltersSlice(): void
    {
        $token = $this->mint(['status' => ['value' => 'paid', 'comparison' => '=']]);

        $response = $this->controller->resolve(
            Request::create('/admin/polysource/f/' . $token . '?index=' . rawurlencode('/admin/orders')),
            $token,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        $target = $response->getTargetUrl();
        self::assertStringStartsWith('/admin/orders?', $target);

        parse_str((string) parse_url($target, \PHP_URL_QUERY), $query);
        self::assertSame(
            ['status' => ['value' => 'paid', 'comparison' => '=']],
            $query['filters'] ?? null,
        );
    }

    #[Test]
    public function preservesTheIndexTargetsOwnQueryParameters(): void
    {
        $token = $this->mint(['stock' => ['value' => '5', 'comparison' => '>']]);

        $response = $this->controller->resolve(
            Request::create('/admin/polysource/f/' . $token . '?index=' . rawurlencode('/admin/orders?page=3&sort=name')),
            $token,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        parse_str((string) parse_url($response->getTargetUrl(), \PHP_URL_QUERY), $query);

        self::assertSame('3', $query['page'] ?? null);
        self::assertSame('name', $query['sort'] ?? null);
        self::assertSame(['value' => '5', 'comparison' => '>'], $query['filters']['stock'] ?? null);
    }
}
