<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Contract test for the datagrid extension blocks introduced in the
 * bridge's crud/index.html.twig (v1.1.2):
 *
 *   - `polysource_datagrid_toolbar` wraps the saved-views dropdown and
 *     the column-visibility toggle;
 *   - `polysource_chips_bar` wraps the active-filters chips include.
 *
 * The promise to hosts is twofold: by default both regions render as
 * before, and a host template extending the bridge index can override
 * either block (typically with an empty body) to remove that region
 * without re-implementing the rest of the index.
 *
 * Rendered through a standalone Twig environment (same approach as
 * FormThemeRenderingTest): the upstream EA index and the chips-bar
 * include are marker stubs, and the Twig functions the template calls
 * are no-op stubs — the assertions only concern which regions of the
 * bridge template make it into the output.
 */
final class DatagridExtensionBlocksTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $bridgeRoot = \dirname(__DIR__, 3);

        $loader = new FilesystemLoader();
        $loader->addPath($bridgeRoot . '/Resources/views', 'PolysourceEasyAdminFilterBridge');
        // The bridge index extends `@!EasyAdmin/...` (splice-bypass) and
        // includes `@EasyAdmin/...` — both resolve to fixture stubs here.
        $loader->addPath(__DIR__ . '/Fixtures/upstream_easyadmin', '!EasyAdmin');
        $loader->addPath(__DIR__ . '/Fixtures/easyadmin_ns', 'EasyAdmin');
        $loader->addPath(__DIR__ . '/Fixtures');

        $this->twig = new Environment($loader, ['strict_variables' => false]);
        $this->twig->addExtension(new TranslationExtension(new Translator('en')));

        // Compile-time stubs for the functions the template references.
        // Only `saved_views_dropdown` and `asset` actually run in these
        // scenarios (the column-visibility branch needs `entities`,
        // which the tests never provide) but Twig resolves every
        // function at compile time.
        $this->twig->addFunction(new TwigFunction(
            'saved_views_dropdown',
            static fn (string $resource): string => '<div id="stub-saved-views"></div>',
            ['is_safe' => ['html']],
        ));
        $this->twig->addFunction(new TwigFunction('polysource_route_exists', static fn (string $route): bool => false));
        $this->twig->addFunction(new TwigFunction('polysource_hidden_columns', static fn (string $resource): array => []));
        $this->twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'stub-token'));
        $this->twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/stub/' . $route));
        $this->twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));
        // `|humanize` ships with Symfony's Twig-Bridge FormExtension;
        // stubbed here to avoid pulling the form rendering runtime in.
        $this->twig->addFilter(new TwigFilter('humanize', static fn (string $text): string => $text));
    }

    /**
     * `ea` shaped closely enough for the index template: the toolbar
     * gate reads `ea.crud`, the chips bar reads `ea.request.query.all`.
     *
     * @return array<string, mixed>
     */
    private function eaContext(): array
    {
        return [
            'ea' => [
                'crud' => ['entityFqcn' => 'App\\Entity\\Stub'],
                'request' => ['query' => ['all' => ['filters' => []]]],
            ],
        ];
    }

    public function testBridgeIndexRendersToolbarAndChipsBarByDefault(): void
    {
        $html = $this->twig->render('@PolysourceEasyAdminFilterBridge/crud/index.html.twig', $this->eaContext());

        self::assertStringContainsString('ea-filter-saved-views-bar', $html);
        self::assertStringContainsString('stub-saved-views', $html);
        self::assertStringContainsString('stub-chips-bar', $html);
        self::assertStringContainsString('<!-- upstream-main -->', $html);
    }

    public function testHostOverridingBlocksEmptyRemovesToolbarAndChipsBar(): void
    {
        $html = $this->twig->render('host_index_blocks_override.html.twig', $this->eaContext());

        self::assertStringNotContainsString('ea-filter-saved-views-bar', $html);
        self::assertStringNotContainsString('stub-saved-views', $html);
        self::assertStringNotContainsString('stub-chips-bar', $html);
        // The rest of the index (EA's own main content) must survive.
        self::assertStringContainsString('<!-- upstream-main -->', $html);
    }
}
