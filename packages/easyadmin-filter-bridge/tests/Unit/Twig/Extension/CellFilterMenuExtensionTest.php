<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\CellFilterMenuExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

#[CoversClass(CellFilterMenuExtension::class)]
final class CellFilterMenuExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheTwoExpectedTwigFunctions(): void
    {
        $extension = new CellFilterMenuExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_cell_filter_menu', $names);
        self::assertContains('polysource_cell_filter_url', $names);
        self::assertCount(2, $names);
    }

    #[Test]
    public function renderProducesDropdownMarkupWithThreeActionLinks(): void
    {
        $extension = new CellFilterMenuExtension(
            $this->makeRequestStack('/admin/orders'),
        );

        $markup = $extension->render('status', 'paid', 'Status');

        $html = (string) $markup;

        self::assertStringContainsString('polysource-cell-filter-menu', $html);
        // Three action items
        self::assertSame(3, substr_count($html, 'class="dropdown-item"'));
        // Eq link — literal " around the value in text content
        self::assertStringContainsString('Filter where Status = "paid"', $html);
        // Neq link
        self::assertStringContainsString('Exclude Status = "paid"', $html);
        // Show-only link
        self::assertStringContainsString('Show only this Status', $html);
    }

    #[Test]
    public function renderReturnsEmptyMarkupForEmptyValues(): void
    {
        $extension = new CellFilterMenuExtension();

        self::assertSame('', (string) $extension->render('status', null));
        self::assertSame('', (string) $extension->render('status', ''));
    }

    #[Test]
    public function urlForEqBuildsTheExpectedSlice(): void
    {
        $extension = new CellFilterMenuExtension(
            $this->makeRequestStack('/admin/orders'),
        );

        $url = $extension->urlFor('status', 'paid', 'eq');

        self::assertSame('/admin/orders?filters%5Bstatus%5D=paid', $url);
    }

    #[Test]
    public function urlForNeqBuildsTheNegatedSlice(): void
    {
        $extension = new CellFilterMenuExtension(
            $this->makeRequestStack('/admin/orders'),
        );

        $url = $extension->urlFor('status', 'paid', 'neq');

        // The neq slice uses the EA expanded shape:
        //   filters[status][comparison]=!=&filters[status][value]=paid
        self::assertStringContainsString('filters%5Bstatus%5D%5Bcomparison%5D=%21%3D', $url);
        self::assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D=paid', $url);
    }

    #[Test]
    public function urlForReplacePreservesNoExistingFilters(): void
    {
        $extension = new CellFilterMenuExtension(
            $this->makeRequestStack('/admin/orders', [
                'filters' => ['country' => 'FR'],
                'sort' => ['createdAt' => 'desc'],
            ]),
        );

        // Replace = drop the existing filters AND query (the entire
        // URL state) and emit just our slice. This is the "Show
        // only this X" behaviour.
        $url = $extension->urlFor('status', 'paid', 'eq', replace: true);

        self::assertStringContainsString('filters%5Bstatus%5D=paid', $url);
        self::assertStringNotContainsString('country', $url);
        self::assertStringNotContainsString('createdAt', $url);
    }

    #[Test]
    public function urlForPreservesUnrelatedQueryWhenNotReplacing(): void
    {
        $extension = new CellFilterMenuExtension(
            $this->makeRequestStack('/admin/orders', [
                'sort' => ['createdAt' => 'desc'],
                'page' => '2',
            ]),
        );

        $url = $extension->urlFor('status', 'paid', 'eq');

        self::assertStringContainsString('filters%5Bstatus%5D=paid', $url);
        self::assertStringContainsString('sort%5BcreatedAt%5D=desc', $url);
        self::assertStringContainsString('page=2', $url);
    }

    /**
     * @param array<string, array<string, string>|string> $query
     */
    private function makeRequestStack(string $path, array $query = []): RequestStack
    {
        $stack = new RequestStack();
        $request = Request::create($path, 'GET');
        foreach ($query as $key => $value) {
            $request->query->set($key, $value);
        }
        $stack->push($request);

        return $stack;
    }
}
