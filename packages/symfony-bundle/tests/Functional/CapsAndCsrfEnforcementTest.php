<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Tests\Functional\App\BulkCapTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Pins the boundary contracts on the action endpoints:
 *
 * - Bulk requests carrying more identifiers than `max_bulk_ids`
 *   are rejected with 400 (configurable safety cap).
 * - POSTs without a `_token` field → 403.
 * - POSTs with a wrong/forged token → 403.
 * - POSTs with a valid token → 302 (redirect back to index after
 *   the action completes).
 *
 * Cf. coverage plan Sprint 0.
 */
final class CapsAndCsrfEnforcementTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return BulkCapTestKernel::class;
    }

    /** @param array<mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function csrfToken(KernelInterface $kernel): string
    {
        $manager = self::getContainer()->get(CsrfTokenManagerInterface::class);
        \assert($manager instanceof CsrfTokenManagerInterface);

        return $manager->getToken(ActionController::CSRF_TOKEN_ID)->getValue();
    }

    #[Test]
    public function bulkActionRejectsRequestExceedingMaxIds(): void
    {
        $kernel = self::bootKernel();
        $token = $this->csrfToken($kernel);

        // The kernel sets max_bulk_ids = 3; submit 4 → expect 400.
        $response = $kernel->handle(
            Request::create(
                '/admin/bulk-cap/batch/noop-bulk',
                'POST',
                ['_token' => $token, 'ids' => ['1', '2', '3', '4']],
            ),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame(400, $response->getStatusCode(), 'Bulk action over the cap must 400');
    }

    #[Test]
    public function bulkActionAcceptsRequestAtMaxIds(): void
    {
        $kernel = self::bootKernel();
        $token = $this->csrfToken($kernel);

        $response = $kernel->handle(
            Request::create(
                '/admin/bulk-cap/batch/noop-bulk',
                'POST',
                ['_token' => $token, 'ids' => ['1', '2', '3']],
            ),
            HttpKernelInterface::MAIN_REQUEST,
        );

        // The action handler runs; we redirect back to the index.
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function inlineActionRejectsRequestWithoutCsrfToken(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create(
                '/admin/bulk-cap/1/noop-bulk',
                'POST',
            ),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame(403, $response->getStatusCode(), 'Missing CSRF token must yield 403');
    }

    #[Test]
    public function inlineActionRejectsRequestWithInvalidCsrfToken(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create(
                '/admin/bulk-cap/1/noop-bulk',
                'POST',
                ['_token' => 'forged-value-no-good'],
            ),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame(403, $response->getStatusCode(), 'Forged CSRF token must yield 403');
    }
}
