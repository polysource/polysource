<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController;
use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Polysource\Filter\ColumnPreference\Storage\InMemoryColumnPreferenceStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Behavioural tests for {@see ColumnPreferenceController}.
 *
 * Uses the REAL {@see ColumnPreferenceService} on the in-memory
 * storage (the service is final, and the persistence contract —
 * "hidden = columns − visible" — is exactly what these tests lock),
 * with a stubbed CSRF manager for the token gate.
 */
#[CoversClass(ColumnPreferenceController::class)]
final class ColumnPreferenceControllerTest extends TestCase
{
    private const RESOURCE = 'App\Entity\Order';

    private ColumnPreferenceService $service;

    protected function setUp(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('jane', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));

        $this->service = new ColumnPreferenceService(
            new InMemoryColumnPreferenceStorage(),
            $tokenStorage,
        );
    }

    private function controller(bool $csrfValid = true): ColumnPreferenceController
    {
        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        return new ColumnPreferenceController($this->service, $csrf);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(array $post, ?string $referer = null): Request
    {
        $request = Request::create(
            '/admin/polysource/column-preferences/' . rawurlencode(self::RESOURCE),
            'POST',
            $post,
        );
        if (null !== $referer) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }

    #[Test]
    public function invalidCsrfTokenIsRejectedWithoutPersisting(): void
    {
        $response = $this->controller(csrfValid: false)->update(
            $this->post(['columns' => ['a', 'b'], 'visible' => []]),
            self::RESOURCE,
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame([], $this->service->hiddenColumns(self::RESOURCE));
    }

    #[Test]
    public function hiddenSetIsColumnsMinusVisible(): void
    {
        $response = $this->controller()->update(
            $this->post([
                'columns' => ['sku', 'name', 'price', 'stock'],
                'visible' => ['sku', 'stock'],
            ]),
            self::RESOURCE,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['name', 'price'], $this->service->hiddenColumns(self::RESOURCE));
    }

    #[Test]
    public function emptyVisibleSubmissionHidesEveryColumn(): void
    {
        $this->controller()->update(
            $this->post(['columns' => ['a', 'b'], 'visible' => []]),
            self::RESOURCE,
        );

        self::assertSame(['a', 'b'], $this->service->hiddenColumns(self::RESOURCE));
    }

    #[Test]
    public function duplicateAndEmptyEntriesAreNormalised(): void
    {
        $this->controller()->update(
            $this->post(['columns' => ['a', 'a', '', 'b'], 'visible' => ['']]),
            self::RESOURCE,
        );

        self::assertSame(['a', 'b'], $this->service->hiddenColumns(self::RESOURCE));
    }

    #[Test]
    public function redirectsBackToSameHostReferer(): void
    {
        $response = $this->controller()->update(
            $this->post(
                ['columns' => ['a'], 'visible' => ['a']],
                referer: 'http://localhost/admin?crudAction=index&page=2',
            ),
            self::RESOURCE,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('http://localhost/admin?crudAction=index&page=2', $response->getTargetUrl());
    }

    #[Test]
    public function foreignHostRefererFallsBackToRoot(): void
    {
        $response = $this->controller()->update(
            $this->post(
                ['columns' => ['a'], 'visible' => ['a']],
                referer: 'https://evil.example/phish',
            ),
            self::RESOURCE,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/', $response->getTargetUrl());
    }

    #[Test]
    public function anonymousSubmissionIsAHarmlessNoOp(): void
    {
        $anonymousService = new ColumnPreferenceService(
            new InMemoryColumnPreferenceStorage(),
            new TokenStorage(),
        );
        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $controller = new ColumnPreferenceController($anonymousService, $csrf);

        $response = $controller->update(
            $this->post(['columns' => ['a', 'b'], 'visible' => []]),
            self::RESOURCE,
        );

        // The service no-ops for anonymous users; the request still
        // redirects cleanly instead of erroring.
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame([], $anonymousService->hiddenColumns(self::RESOURCE));
    }
}
