<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\SavedViewStorageInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Pins the security properties of the v0.9.0 controller refresh:
 *
 *   - Missing or invalid CSRF token → 403 before any state mutation
 *   - Token id is scoped per operation (create vs delete vs default)
 *     so a leaked create-token cannot be replayed against delete
 */
#[CoversClass(SavedViewController::class)]
final class SavedViewControllerSecurityTest extends TestCase
{
    #[Test]
    public function createRejectsMissingCsrfToken(): void
    {
        $controller = $this->makeController(expectedTokenId: 'polysource_saved_view_create', acceptToken: false);
        $request = Request::create('/admin/saved-views', 'POST');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');
        $controller->create($request);
    }

    #[Test]
    public function deleteRejectsInvalidCsrfToken(): void
    {
        $controller = $this->makeController(expectedTokenId: 'polysource_saved_view_delete', acceptToken: false);
        $request = Request::create('/admin/saved-views/abc/delete', 'POST');
        $request->request->set('_token', 'forged');

        $this->expectException(AccessDeniedHttpException::class);
        $controller->delete('abc', $request);
    }

    #[Test]
    public function toggleDefaultRejectsInvalidCsrfToken(): void
    {
        $controller = $this->makeController(expectedTokenId: 'polysource_saved_view_toggle_default', acceptToken: false);
        $request = Request::create('/admin/saved-views/abc/default', 'POST');
        $request->request->set('_token', 'forged');

        $this->expectException(AccessDeniedHttpException::class);
        $controller->toggleDefault('abc', $request);
    }

    #[Test]
    public function rejectsWhenCsrfTokenManagerNotWired(): void
    {
        // Hosts that haven't enabled framework.csrf_protection get
        // null injected. The controller fails closed at request time
        // with a 403 explaining the misconfiguration — better than
        // either silently accepting unauthenticated mutations or
        // breaking DI compilation at boot.
        $service = new SavedViewService(
            storage: $this->createMock(SavedViewStorageInterface::class),
            authChecker: $this->createMock(AuthorizationCheckerInterface::class),
            tokenStorage: $this->createMock(TokenStorageInterface::class),
        );
        $security = $this->createMock(Security::class);

        $controller = new SavedViewController(
            service: $service,
            security: $security,
            csrfTokenManager: null,
        );

        $request = Request::create('/admin/saved-views', 'POST');
        $request->request->set('_token', 'anything');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('CSRF protection not configured');
        $controller->create($request);
    }

    #[Test]
    public function createTokenCannotBeReplayedAgainstDelete(): void
    {
        // The csrf manager is configured to ONLY accept the
        // `polysource_saved_view_create` id. A request to /delete with
        // the same physical token string is rejected because the id
        // (which is part of the validated tuple) doesn't match.
        $controller = $this->makeController(expectedTokenId: 'polysource_saved_view_create', acceptToken: true);
        $request = Request::create('/admin/saved-views/abc/delete', 'POST');
        $request->request->set('_token', 'token-string');

        $this->expectException(AccessDeniedHttpException::class);
        $controller->delete('abc', $request);
    }

    private function makeController(string $expectedTokenId, bool $acceptToken): SavedViewController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf
            ->method('isTokenValid')
            ->willReturnCallback(static fn (CsrfToken $token): bool => $acceptToken && $token->getId() === $expectedTokenId);

        // SavedViewService is final — can't mock. Construct a real
        // one with mocked deps. The CSRF check throws *before* any
        // service method is called, so no behaviour is required.
        $service = new SavedViewService(
            storage: $this->createMock(SavedViewStorageInterface::class),
            authChecker: $this->createMock(AuthorizationCheckerInterface::class),
            tokenStorage: $this->createMock(TokenStorageInterface::class),
        );
        $security = $this->createMock(Security::class);

        return new SavedViewController(
            service: $service,
            security: $security,
            csrfTokenManager: $csrf,
        );
    }
}
