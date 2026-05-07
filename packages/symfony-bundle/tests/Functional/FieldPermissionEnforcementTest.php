<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Tests\Fixture\AttributeMapPermission;
use Polysource\Bundle\Tests\Functional\App\FieldPermissionTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Asserts that fields declared with `setPermission('X')` are filtered
 * out of the rendered index table when the current user lacks the X
 * attribute, AND included when the user has it.
 *
 * The Field API has carried a `permission` slot on FieldDto since
 * 0.1.0 but the bundle's `ControllerSupport::collectFields` was
 * skipping the check — every field rendered regardless of role. This
 * test pins the contract going forward.
 *
 * Cf. coverage plan Sprint 0 — EA non-regression matrix B8.
 */
final class FieldPermissionEnforcementTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return FieldPermissionTestKernel::class;
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

    #[Test]
    public function fieldWithDeniedPermissionIsHiddenFromIndex(): void
    {
        $kernel = self::bootKernel();
        $perms = $kernel->getContainer()->get(AttributeMapPermission::class);
        \assert($perms instanceof AttributeMapPermission);
        $perms->setMap(['SECRET' => false]);

        $response = $kernel->handle(
            Request::create('/admin/gated', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(200, $response->getStatusCode(), 'Resource access must succeed');
        $body = (string) $response->getContent();

        // The unrestricted column header MUST render.
        self::assertStringContainsString('name', $body, 'Unrestricted field "name" must appear in the table');

        // The gated column MUST NOT render — neither the column header
        // nor the cell value (the salary "4200") should leak.
        self::assertStringNotContainsString('salary', $body, 'Gated field "salary" header must not render when SECRET denied');
        self::assertStringNotContainsString('4200', $body, 'Gated field "salary" value must not leak');
    }

    #[Test]
    public function fieldWithGrantedPermissionIsVisibleOnIndex(): void
    {
        $kernel = self::bootKernel();
        $perms = $kernel->getContainer()->get(AttributeMapPermission::class);
        \assert($perms instanceof AttributeMapPermission);
        $perms->setMap(['SECRET' => true]);

        $response = $kernel->handle(
            Request::create('/admin/gated', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('name', $body);
        self::assertStringContainsString('salary', $body, 'Gated field "salary" header must appear when SECRET granted');
        self::assertStringContainsString('4200', $body, 'Gated field "salary" value must render');
    }
}
