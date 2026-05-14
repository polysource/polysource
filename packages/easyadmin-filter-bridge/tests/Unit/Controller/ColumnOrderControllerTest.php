<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Polysource\Filter\ColumnPreference\Storage\InMemoryColumnPreferenceStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[CoversClass(ColumnOrderController::class)]
final class ColumnOrderControllerTest extends TestCase
{
    #[Test]
    public function returnsForbiddenWhenCsrfTokenIsInvalid(): void
    {
        $controller = $this->makeController(validToken: 'ok');
        $request = $this->requestWith(token: 'bad');

        $response = $controller->move($request, 'orders');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function returnsBadRequestForInvalidDirection(): void
    {
        $controller = $this->makeController();
        $request = $this->requestWith(direction: 'up');

        $response = $controller->move($request, 'orders');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function returnsBadRequestForEmptyProperty(): void
    {
        $controller = $this->makeController();
        $request = $this->requestWith(property: '');

        $response = $controller->move($request, 'orders');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function returnsBadRequestWhenColumnsSliceIsMissing(): void
    {
        $controller = $this->makeController();
        $request = $this->requestWith(columns: []);

        $response = $controller->move($request, 'orders');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function moveLeftSwapsThePropertyWithItsPredecessor(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $controller = $this->makeController(storage: $storage);
        $request = $this->requestWith(direction: 'left', property: 'status', columns: ['id', 'status', 'reference']);

        $response = $controller->move($request, 'orders');

        self::assertInstanceOf(RedirectResponse::class, $response);
        $saved = $storage->find('alice', 'orders');
        self::assertNotNull($saved);
        self::assertSame(['status', 'id', 'reference'], $saved->orderedColumns);
    }

    #[Test]
    public function moveRightSwapsThePropertyWithItsSuccessor(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $controller = $this->makeController(storage: $storage);
        $request = $this->requestWith(direction: 'right', property: 'status', columns: ['id', 'status', 'reference']);

        $response = $controller->move($request, 'orders');

        self::assertInstanceOf(RedirectResponse::class, $response);
        $saved = $storage->find('alice', 'orders');
        self::assertNotNull($saved);
        self::assertSame(['id', 'reference', 'status'], $saved->orderedColumns);
    }

    #[Test]
    public function movingLeftWhenAlreadyFirstPersistsTheUnchangedOrder(): void
    {
        // Out-of-bounds moves are no-ops at the algorithm level — the
        // service still persists the (unchanged) list so the override
        // becomes explicit. That's documented behaviour.
        $storage = new InMemoryColumnPreferenceStorage();
        $controller = $this->makeController(storage: $storage);
        $request = $this->requestWith(direction: 'left', property: 'id', columns: ['id', 'status', 'reference']);

        $response = $controller->move($request, 'orders');

        self::assertInstanceOf(RedirectResponse::class, $response);
        $saved = $storage->find('alice', 'orders');
        self::assertNotNull($saved);
        self::assertSame(['id', 'status', 'reference'], $saved->orderedColumns);
    }

    private function makeController(
        ?InMemoryColumnPreferenceStorage $storage = null,
        string $validToken = 'ok',
    ): ColumnOrderController {
        $storage ??= new InMemoryColumnPreferenceStorage();

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('alice', null), 'main'));

        $service = new ColumnPreferenceService($storage, $tokenStorage);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')
            ->willReturnCallback(static fn (CsrfToken $token): bool => $validToken === $token->getValue())
        ;

        return new ColumnOrderController($service, $csrf);
    }

    /**
     * @param list<string> $columns
     */
    private function requestWith(
        string $property = 'status',
        string $direction = 'left',
        array $columns = ['id', 'status', 'reference'],
        string $token = 'ok',
    ): Request {
        return Request::create('/admin/polysource/column-order/orders/move', 'GET', [
            '_token' => $token,
            'property' => $property,
            'direction' => $direction,
            'columns' => $columns,
        ]);
    }
}
