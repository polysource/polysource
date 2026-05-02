<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Tests\Functional\App\DeniedTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Verifies that controllers honour PermissionInterface — denying access
 * yields 403 instead of falling through to the data source.
 */
final class PermissionEnforcementTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return DeniedTestKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    #[Test]
    public function indexRouteReturns403WhenPermissionIsDenied(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags', 'GET'));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function detailRouteReturns403WhenPermissionIsDenied(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags/1', 'GET'));

        self::assertSame(403, $response->getStatusCode());
    }
}
