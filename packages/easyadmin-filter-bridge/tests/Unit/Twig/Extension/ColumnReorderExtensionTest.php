<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnReorderExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\TwigFunction;

#[CoversClass(ColumnReorderExtension::class)]
final class ColumnReorderExtensionTest extends TestCase
{
    private const RESOURCE = 'App\Entity\Order';
    private const COLUMNS = ['sku', 'name', 'price'];

    private function extension(): ColumnReorderExtension
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                \assert(\is_string($params['property']) && \is_string($params['direction']));

                return '/move?property=' . $params['property'] . '&direction=' . $params['direction'];
            },
        );

        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('id', 'stub-token'));

        return new ColumnReorderExtension($urlGenerator, $csrf);
    }

    #[Test]
    public function exposesTheReorderButtonsFunction(): void
    {
        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            (new ColumnReorderExtension())->getFunctions(),
        );

        self::assertSame(['polysource_column_reorder_buttons'], $names);
    }

    #[Test]
    public function unwiredExtensionEmitsEmptyMarkupInsteadOfCrashing(): void
    {
        $html = (string) (new ColumnReorderExtension())->renderButtons(self::RESOURCE, 'name', self::COLUMNS);

        self::assertSame('', $html);
    }

    #[Test]
    public function middleColumnGetsTwoEnabledDirectionButtons(): void
    {
        $html = (string) $this->extension()->renderButtons(self::RESOURCE, 'name', self::COLUMNS);

        self::assertStringContainsString('polysource-column-reorder', $html);
        self::assertStringContainsString('direction=left', $html);
        self::assertStringContainsString('direction=right', $html);
        self::assertStringNotContainsString('disabled', $html);
    }

    #[Test]
    public function firstColumnDisablesTheLeftButton(): void
    {
        $html = (string) $this->extension()->renderButtons(self::RESOURCE, 'sku', self::COLUMNS);

        // Exactly one disabled anchor (the ← one), keyboard-skipped.
        self::assertSame(1, substr_count($html, 'aria-disabled="true"'));
        self::assertMatchesRegularExpression('/direction=left[^>]*disabled/', $html);
        self::assertDoesNotMatchRegularExpression('/direction=right[^>]*disabled/', $html);
    }

    #[Test]
    public function lastColumnDisablesTheRightButton(): void
    {
        $html = (string) $this->extension()->renderButtons(self::RESOURCE, 'price', self::COLUMNS);

        self::assertSame(1, substr_count($html, 'aria-disabled="true"'));
        self::assertMatchesRegularExpression('/direction=right[^>]*disabled/', $html);
        self::assertDoesNotMatchRegularExpression('/direction=left[^>]*disabled/', $html);
    }
}
