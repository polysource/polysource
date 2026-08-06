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
        // The full translated string is escaped once before interpolation
        // (since v0.9.2 i18n) — literal quotes render as &quot;.
        self::assertStringContainsString('Filter where Status = &quot;paid&quot;', $html);
        // Neq link
        self::assertStringContainsString('Exclude Status = &quot;paid&quot;', $html);
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

        self::assertSame('/admin/orders?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D=paid', $url);
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

        self::assertStringContainsString('filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D=paid', $url);
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

        self::assertStringContainsString('filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D=paid', $url);
        self::assertStringContainsString('sort%5BcreatedAt%5D=desc', $url);
        self::assertStringContainsString('page=2', $url);
    }

    #[Test]
    public function renderUsesFilterValueOverrideForUrlButDisplayValueForLabel(): void
    {
        // EntityFilter case: display string "Jane Doe #42" but the
        // filter value must be the entity primary key "42". The
        // host passes both — render shows the display string in the
        // dropdown label and emits the primary key in the URL.
        $extension = new CellFilterMenuExtension(
            requestStack: $this->makeRequestStack('/admin/orders'),
        );

        $html = (string) $extension->render(
            property: 'customer',
            value: 'Jane Doe #42',
            label: 'Client',
            filterValue: 42,
        );

        // Display label preserved in the dropdown menu items. The
        // literal quotes around the value are part of the heredoc
        // template — not HTML-escaped because they aren't inside
        // an attribute.
        self::assertStringContainsString('Filter where Client = &quot;Jane Doe #42&quot;', $html);
        // URL uses the primary key, not the display label.
        self::assertStringContainsString('filters%5Bcustomer%5D%5Bvalue%5D=42', $html);
        self::assertStringNotContainsString('filters%5Bcustomer%5D%5Bvalue%5D=Jane', $html);
    }

    #[Test]
    public function renderFallsBackToValueWhenNoFilterValueOverride(): void
    {
        // Existing behaviour for scalar columns: display value IS
        // the filter value. No regression.
        $extension = new CellFilterMenuExtension(
            requestStack: $this->makeRequestStack('/admin/orders'),
        );

        $html = (string) $extension->render('status', 'paid');

        self::assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D=paid', $html);
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
