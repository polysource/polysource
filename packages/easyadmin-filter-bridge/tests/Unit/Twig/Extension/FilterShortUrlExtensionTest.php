<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterShortUrlExtension;
use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Polysource\Filter\FilterUrlToken\Storage\InMemoryFilterUrlTokenStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\TwigFunction;

#[CoversClass(FilterShortUrlExtension::class)]
final class FilterShortUrlExtensionTest extends TestCase
{
    private const RESOURCE = 'App\Entity\Order';

    private FilterUrlTokenService $service;

    protected function setUp(): void
    {
        $this->service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());
    }

    private function extension(?string $currentUrl): FilterShortUrlExtension
    {
        $requestStack = new RequestStack();
        if (null !== $currentUrl) {
            $requestStack->push(Request::create($currentUrl));
        }

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                \assert(\is_string($params['token']) && \is_string($params['index']));

                return 'https://host.example/admin/polysource/f/' . $params['token'] . '?index=' . rawurlencode($params['index']);
            },
        );

        return new FilterShortUrlExtension($this->service, $urlGenerator, $requestStack);
    }

    #[Test]
    public function exposesTheTwoShortUrlFunctions(): void
    {
        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            (new FilterShortUrlExtension())->getFunctions(),
        );

        self::assertSame(['polysource_filter_short_url', 'polysource_filter_share_button'], $names);
    }

    #[Test]
    public function unwiredExtensionReturnsEmptyStringAndEmptyButton(): void
    {
        $extension = new FilterShortUrlExtension();

        self::assertSame('', $extension->shortUrl(self::RESOURCE));
        self::assertSame('', (string) $extension->renderShareButton(self::RESOURCE));
    }

    #[Test]
    public function noActiveFiltersMeansNoShortUrlAndNoButton(): void
    {
        $extension = $this->extension('/admin/orders?page=2');

        self::assertSame('', $extension->shortUrl(self::RESOURCE));
        self::assertSame('', (string) $extension->renderShareButton(self::RESOURCE));
    }

    #[Test]
    public function activeFiltersProduceAResolvableShortUrl(): void
    {
        $extension = $this->extension('/admin/orders?filters%5Bstatus%5D%5Bvalue%5D=paid');

        $url = $extension->shortUrl(self::RESOURCE);

        self::assertStringStartsWith('https://host.example/admin/polysource/f/', $url);
        self::assertStringContainsString('index=' . rawurlencode('/admin/orders'), $url);

        // The token embedded in the URL must resolve back to the
        // exact filter slice through the same service.
        preg_match('#/f/([a-f0-9]{12})#', $url, $matches);
        self::assertArrayHasKey(1, $matches, 'Short URL carries no 12-hex token: ' . $url);
        $record = $this->service->resolve($matches[1]);
        self::assertNotNull($record);
        self::assertSame(self::RESOURCE, $record->resourceName);
        self::assertSame(['status' => ['value' => 'paid']], $record->filtersSlice);
    }

    #[Test]
    public function shareButtonEmbedsTheUrlAndDefaultLabel(): void
    {
        $extension = $this->extension('/admin/orders?filters%5Bstatus%5D%5Bvalue%5D=paid');

        $html = (string) $extension->renderShareButton(self::RESOURCE);

        self::assertStringContainsString('polysource-filter-share', $html);
        self::assertStringContainsString('data-polysource-share-url="https://host.example/admin/polysource/f/', $html);
        self::assertStringContainsString('Copy share link', $html);
    }

    #[Test]
    public function shareButtonHonoursACustomLabel(): void
    {
        $extension = $this->extension('/admin/orders?filters%5Bstatus%5D%5Bvalue%5D=paid');

        $html = (string) $extension->renderShareButton(self::RESOURCE, 'Partager & copier');

        self::assertStringContainsString('Partager &amp; copier', $html);
        self::assertStringNotContainsString('Copy share link', $html);
    }
}
