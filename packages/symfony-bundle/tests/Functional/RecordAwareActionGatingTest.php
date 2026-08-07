<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Tests\Functional\App\RecordGatedTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Per-record action gating (v1.1.0):
 *
 *  - `isDisplayed()` receives `record` / `subject` / `page` on the
 *    index and detail pages, so an action can hide on some rows;
 *  - the permission check receives the DataRecord as voter subject,
 *    both for rendering AND (authoritatively) on the POST endpoint;
 *  - `subject` carries `DataRecord::$rawSource` — regression guard
 *    for the workflow-bridge transitions, which were invisible when
 *    the context was collected empty.
 *
 * Fixture matrix: record 1 (open / owned) shows everything;
 * record 2 (done / foreign) hides status-gated + owner-gated.
 */
final class RecordAwareActionGatingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return RecordGatedTestKernel::class;
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
    public function isDisplayedReceivesRecordContextOnIndex(): void
    {
        $body = $this->renderIndex();

        self::assertSame(
            1,
            substr_count($body, 'data-polysource-action="inline-status-gated"'),
            'status-gated must render on the open record only (isDisplayed sees the record)',
        );
    }

    #[Test]
    public function permissionVoterReceivesRecordSubjectOnIndex(): void
    {
        $body = $this->renderIndex();

        self::assertSame(
            1,
            substr_count($body, 'data-polysource-action="inline-owner-gated"'),
            'owner-gated must render on the owned record only (voter sees the record subject)',
        );
    }

    #[Test]
    public function isDisplayedReceivesRawSourceAsSubject(): void
    {
        $body = $this->renderIndex();

        self::assertSame(
            2,
            substr_count($body, 'data-polysource-action="inline-subject-required"'),
            'subject-required must render on BOTH rows — regression: workflow-style actions need $context[subject]',
        );
    }

    #[Test]
    public function detailPageGetsRecordAwareActions(): void
    {
        $kernel = self::bootKernel();

        $open = (string) $kernel->handle(
            Request::create('/admin/record-gated/1', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        )->getContent();
        $done = (string) $kernel->handle(
            Request::create('/admin/record-gated/2', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        )->getContent();

        self::assertStringContainsString('inline-status-gated', $open, 'Detail of the open record must show status-gated');
        self::assertStringNotContainsString('inline-status-gated', $done, 'Detail of the done record must hide status-gated');
    }

    #[Test]
    public function executingActionDeniedOnForeignRecordReturns403(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create('/admin/record-gated/2/owner-gated', 'POST', ['_token' => $this->csrfToken()]),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame(403, $response->getStatusCode(), 'POST on a record the voter denies must 403 even though the attribute is granted on other records');
    }

    #[Test]
    public function executingActionGrantedOnOwnedRecordRedirects(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create('/admin/record-gated/1/owner-gated', 'POST', ['_token' => $this->csrfToken()]),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    private function renderIndex(): string
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create('/admin/record-gated', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getContent();
    }

    private function csrfToken(): string
    {
        $manager = self::getContainer()->get(CsrfTokenManagerInterface::class);
        \assert($manager instanceof CsrfTokenManagerInterface);

        return $manager->getToken(ActionController::CSRF_TOKEN_ID)->getValue();
    }
}
