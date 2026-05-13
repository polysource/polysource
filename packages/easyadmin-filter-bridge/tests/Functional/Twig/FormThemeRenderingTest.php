<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Twig;

use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Renders every enhanced filter widget through a real Twig + Symfony
 * Form environment and asserts the wrapper-and-delegate contract.
 *
 * Specifically:
 *
 * 1. Each `polysource_enhanced_*_filter_widget` block wraps its content
 *    in a `<div class="polysource-filter polysource-filter--<type>"
 *    data-controller="polysource--filter" data-…>`.
 *
 * 2. The inner content is produced by the upstream block
 *    (`form_widget_compound`, `ea_numeric_filter_widget`,
 *    `ea_datetime_filter_widget`, or `choice_widget`) — we don't
 *    duplicate any of EA's markup, so a host app that has customised
 *    the upstream theme automatically inherits those customisations.
 *
 * 3. Option-driven UI elements (preset buttons, quick-range buttons,
 *    clear button) appear when the corresponding option is set, with
 *    `data-action="polysource--filter#…"` so the Stimulus controller
 *    binds them.
 *
 * The Twig environment is built minimally with both upstream EA's
 * `crud/form_theme.html.twig` and our bridge theme loaded — no Symfony
 * kernel boot needed.
 */
final class FormThemeRenderingTest extends TestCase
{
    private const BRIDGE_THEME = 'form/polysource_filter_theme.html.twig';
    private const FORM_DIV_LAYOUT = 'form_div_layout.html.twig';
    private const UPSTREAM_THEME_STUB = 'upstream_theme_stub.html.twig';

    private FormFactoryInterface $formFactory;
    private FormRenderer $formRenderer;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->formFactory = Forms::createFormFactoryBuilder()->getFormFactory();

        $bridgeRoot = \dirname(__DIR__, 3);
        $twigBridgeRoot = $this->findTwigBridge();

        $loader = new FilesystemLoader();
        $loader->addPath($bridgeRoot . '/Resources/views', 'PolysourceEasyAdminFilterBridge');
        $loader->addPath($twigBridgeRoot . '/Resources/views/Form');
        $loader->addPath(__DIR__ . '/Fixtures');
        $loader->addPath($bridgeRoot . '/Resources/views');

        $this->twig = new Environment($loader, ['strict_variables' => false]);

        $translator = new Translator('en');
        $this->twig->addExtension(new TranslationExtension($translator));
        $this->twig->addExtension(new FormExtension());

        $rendererEngine = new TwigRendererEngine([
            self::FORM_DIV_LAYOUT,
            self::UPSTREAM_THEME_STUB,
            self::BRIDGE_THEME,
        ], $this->twig);
        $this->formRenderer = new FormRenderer($rendererEngine);
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn () => $this->formRenderer,
        ]));
    }

    public function testTextFilterWrapsWithDataMinLength(): void
    {
        $form = $this->formFactory->create(EnhancedTextFilterType::class, null, [
            'min_length' => 3,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--text"', $html);
        self::assertStringContainsString('data-controller="polysource--filter"', $html);
        self::assertStringContainsString('data-polysource--filter-min-length-value="3"', $html);
    }

    public function testNumericFilterWrapsWithStepAttribute(): void
    {
        // Since v0.2.0 the numeric filter no longer ships quick_ranges
        // (cf. ADR-027 + ADR-028). The widget exposes `step` as the
        // sole option-driven data attribute; hosts who need range
        // shortcuts add them in their own CRUD template.
        $form = $this->formFactory->create(EnhancedNumericFilterType::class, null, [
            'value_type' => NumberType::class,
            'step' => 0.5,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--numeric"', $html);
        self::assertStringContainsString('data-polysource--filter-step-value="0.5"', $html);
        self::assertStringNotContainsString('polysource--filter#applyQuickRange', $html);
        self::assertStringNotContainsString('polysource-quick-range', $html);
    }

    public function testDatetimeFilterWrapsWithDataController(): void
    {
        // Since v0.2.0 the datetime filter no longer ships presets or
        // show_clear options (cf. ADR-027 + ADR-028). The widget just
        // wraps the upstream `ea_datetime_filter_widget` in a
        // `data-controller` div so the Stimulus controller can still
        // bind for non-removed features (subpanel, chip integration).
        $form = $this->formFactory->create(EnhancedDateTimeFilterType::class, null, [
            'value_type' => \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--datetime"', $html);
        self::assertStringContainsString('data-controller="polysource--filter"', $html);
        self::assertStringNotContainsString('polysource--filter#applyPreset', $html);
        self::assertStringNotContainsString('polysource--filter#clearValues', $html);
        self::assertStringNotContainsString('data-polysource--filter-show-clear-value', $html);
        self::assertStringNotContainsString('data-polysource--filter-presets-value', $html);
    }

    public function testBooleanFilterWrapsChoiceWidget(): void
    {
        $form = $this->formFactory->create(EnhancedBooleanFilterType::class, null, [
            'include_null' => true,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--boolean"', $html);
        self::assertStringContainsString('data-polysource--filter-include-null-value="true"', $html);
        // 3 radio inputs (true / false / null) — exact `value` depends on
        // Symfony ChoiceList's serialisation which we don't pin here;
        // the contract is "three expanded radios + null label appears".
        self::assertSame(3, substr_count($html, '<input type="radio"'));
        self::assertStringContainsString('label.null', $html);
    }

    public function testChoiceFilterWrapsWithInlineFlag(): void
    {
        // ChoiceFilterType's parent (ComparisonFilterType) requires
        // `value_type` and forwards `choices` via `value_type_options`.
        $form = $this->formFactory->create(EnhancedChoiceFilterType::class, null, [
            'value_type' => \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class,
            'value_type_options' => ['choices' => ['Yes' => 'yes', 'No' => 'no']],
            'inline' => true,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--choice"', $html);
        self::assertStringContainsString('data-polysource--filter-inline-value="true"', $html);
    }

    public function testComparisonFilterExposesWhitelistInDataset(): void
    {
        $form = $this->formFactory->create(EnhancedComparisonFilterType::class, null, [
            'value_type' => NumberType::class,
            'comparisons' => ['=', '>=', '<='],
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--comparison"', $html);
        self::assertStringContainsString('data-polysource--filter-comparisons-value="', $html);
        // The whitelist is JSON-encoded then HTML-attr-escaped: `=` becomes
        // `&#x3D;`, `>=` becomes `&gt;&#x3D;`, etc. We pin on the marker
        // sequence rather than the exact escaping (which depends on the
        // Twig escape strategy) — the JSON value is what JS consumes.
        // Twig's `e('html_attr')` encodes `=` as `&#x3D;`, `>` as `&gt;`,
        // `<` as `&lt;`. The whitelist is therefore JSON-then-HTML-attr-
        // encoded — we pin on the encoded sequence.
        self::assertMatchesRegularExpression(
            '/data-polysource--filter-comparisons-value="[^"]*&#x3D;[^"]*&gt;&#x3D;[^"]*&lt;&#x3D;/',
            $html,
            'Whitelist (=, >=, <=) must appear in the data attribute',
        );
    }

    public function testArrayFilterWrapsWithChipDisplayFlag(): void
    {
        $form = $this->formFactory->create(EnhancedArrayFilterType::class, null, [
            'chip_display' => true,
        ]);

        $html = $this->renderWidget($form);

        self::assertStringContainsString('class="polysource-filter polysource-filter--array"', $html);
        self::assertStringContainsString('data-polysource--filter-chip-display-value="true"', $html);
    }

    private function renderWidget(\Symfony\Component\Form\FormInterface $form): string
    {
        return $this->formRenderer->searchAndRenderBlock($form->createView(), 'widget');
    }

    private function findTwigBridge(): string
    {
        $candidates = [
            \dirname(__DIR__, 5) . '/vendor/symfony/twig-bridge',
            \dirname(__DIR__, 4) . '/vendor/symfony/twig-bridge',
            \dirname(__DIR__, 5) . '/examples/easyadmin-bridge-demo/vendor/symfony/twig-bridge',
        ];
        foreach ($candidates as $path) {
            if (is_dir($path)) {
                $real = realpath($path);

                return false !== $real ? $real : $path;
            }
        }
        self::fail('Could not locate symfony/twig-bridge under any expected vendor path');
    }
}
