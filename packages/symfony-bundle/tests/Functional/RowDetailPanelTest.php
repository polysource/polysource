<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Tests\Functional\App\RowDetailTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Native expandable row details (v1.1.0):
 * `GET /{prefix}/{slug}/{id}/detail-panel` + the index chevron gate.
 *
 * Fixture matrix (cf. RowDetailTestResource): record 1 fully
 * available, record 2 hasRowDetail=false, record 3 denied by the
 * per-record RECORD_OWNER permission.
 */
final class RowDetailPanelTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return RowDetailTestKernel::class;
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
    public function indexRendersChevronOnlyForAvailableRows(): void
    {
        $body = $this->body('GET', '/admin/row-detailed', 200);

        self::assertSame(
            1,
            substr_count($body, 'data-polysource-action="row-detail"'),
            'Exactly one row qualifies: record 2 has no detail, record 3 is permission-denied',
        );
        self::assertStringContainsString('/admin/row-detailed/1/detail-panel', $body);
        // Lazy contract: no detail content in the initial render.
        self::assertStringNotContainsString('native-row-detail', $body);
    }

    #[Test]
    public function fragmentModeRendersTheResourceTemplateWithContext(): void
    {
        $body = $this->body('GET', '/admin/row-detailed/1/detail-panel?fragment=1', 200);

        self::assertStringContainsString('Native detail for first', $body);
        self::assertStringContainsString('Shouted: FIRST', $body, 'RowDetail context must reach the template');
        self::assertStringNotContainsString('polysource_layout', $body);
    }

    #[Test]
    public function pageModeWrapsTheFragmentInTheLayout(): void
    {
        $body = $this->body('GET', '/admin/row-detailed/1/detail-panel', 200);

        self::assertStringContainsString('Native detail for first', $body);
        self::assertStringContainsString('href="/admin/row-detailed"', $body, 'No-JS baseline must link back to the listing');
    }

    #[Test]
    public function rowWithoutDetailIs404(): void
    {
        $this->body('GET', '/admin/row-detailed/2/detail-panel?fragment=1', 404);
    }

    #[Test]
    public function permissionDeniedPerRecordIs403(): void
    {
        $this->body('GET', '/admin/row-detailed/3/detail-panel?fragment=1', 403);
    }

    #[Test]
    public function resourceWithoutInterfaceIs404(): void
    {
        // TestResource ("flags", registered by the parent kernel)
        // does not implement HasRowDetailsInterface.
        $this->body('GET', '/admin/flags/1/detail-panel?fragment=1', 404);
    }

    #[Test]
    public function panelResponseIsNoStore(): void
    {
        $kernel = self::bootKernel();
        $response = $kernel->handle(
            Request::create('/admin/row-detailed/1/detail-panel?fragment=1', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function body(string $method, string $uri, int $expectedStatus): string
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create($uri, $method),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame($expectedStatus, $response->getStatusCode(), \sprintf('%s %s', $method, $uri));

        return (string) $response->getContent();
    }
}
