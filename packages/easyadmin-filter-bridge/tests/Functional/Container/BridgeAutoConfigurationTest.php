<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Container;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\ArrayFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\BooleanFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ChoiceFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ComparisonFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\DateTimeFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\EntityFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\NumericFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\TextFilterEnhancer;
use Polysource\EasyAdminFilterBridge\DependencyInjection\PolysourceEasyAdminFilterBridgeExtension;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\AbstractType;

/**
 * Integration test that closes the loop on the seam validation.
 *
 * Unit tests prove that our Configurators do the right thing **if**
 * EasyAdmin's `FilterFactory` calls them. This test proves that:
 *
 * 1. Our DI extension registers the services with `setAutoconfigured(true)`.
 * 2. With Symfony's `registerForAutoconfiguration(FilterConfiguratorInterface::class)`
 *    declared (which EasyAdmin does, cf. `EasyAdminExtension.php:47-48`), the
 *    `ea.filter_configurator` tag is automatically applied to our services.
 * 3. With Symfony's `registerForAutoconfiguration(AbstractType::class)`,
 *    the `form.type` tag is applied to our enhanced FormTypes.
 *
 * We use a minimal `ContainerBuilder` rather than a full kernel because
 * the contract we're verifying is local to our extension — no Symfony
 * Form/Twig/Doctrine bagage needed.
 */
final class BridgeAutoConfigurationTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        // Replicate exactly what EasyAdmin's EasyAdminExtension::load() does
        // (cf. EasyAdminExtension.php:47-48):
        $this->container
            ->registerForAutoconfiguration(FilterConfiguratorInterface::class)
            ->addTag('ea.filter_configurator')
        ;

        // Replicate what Symfony FrameworkBundle does for FormTypes when
        // framework.form is enabled:
        $this->container
            ->registerForAutoconfiguration(AbstractType::class)
            ->addTag('form.type')
        ;

        // Now load our extension — this is the code under test.
        (new PolysourceEasyAdminFilterBridgeExtension())->load([], $this->container);

        // Mark our services public for the test; otherwise the
        // RemoveUnusedDefinitionsPass strips them at compile time
        // because no other service in this minimal container consumes
        // them. In a real app, EasyAdmin's FilterFactory references
        // the Configurators (via tagged_iterator) and Symfony Form
        // references the FormTypes — so they survive.
        foreach ([
            DateTimeFilterEnhancer::class,
            BooleanFilterEnhancer::class,
            TextFilterEnhancer::class,
            NumericFilterEnhancer::class,
            ChoiceFilterEnhancer::class,
            ComparisonFilterEnhancer::class,
            ArrayFilterEnhancer::class,
            EntityFilterEnhancer::class,
            EnhancedDateTimeFilterType::class,
            EnhancedBooleanFilterType::class,
            EnhancedTextFilterType::class,
            EnhancedNumericFilterType::class,
            EnhancedChoiceFilterType::class,
            EnhancedComparisonFilterType::class,
            EnhancedArrayFilterType::class,
            EnhancedEntityFilterType::class,
        ] as $serviceId) {
            if ($this->container->hasDefinition($serviceId)) {
                $this->container->getDefinition($serviceId)->setPublic(true);
            }
        }
    }

    public function testDatetimeConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue(
            $this->container->hasDefinition(DateTimeFilterEnhancer::class),
            'DateTimeFilterEnhancer must be registered as a service by our DI extension',
        );

        $definition = $this->container->getDefinition(DateTimeFilterEnhancer::class);
        self::assertTrue(
            $definition->isAutoconfigured(),
            'Service must be autoconfigured so EasyAdmin\'s registerForAutoconfiguration applies the tag',
        );

        // After the container compiles, the autoconfiguration kicks in and
        // applies ea.filter_configurator tag on our service.
        $this->container->compile();

        $definition = $this->container->getDefinition(DateTimeFilterEnhancer::class);
        self::assertArrayHasKey(
            'ea.filter_configurator',
            $definition->getTags(),
            'After compilation, ea.filter_configurator tag must be auto-applied — that is what makes EasyAdmin\'s FilterFactory pick up our Configurator',
        );
    }

    public function testBooleanConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(BooleanFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(BooleanFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(BooleanFilterEnhancer::class)->getTags(),
        );
    }

    public function testTextConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(TextFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(TextFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(TextFilterEnhancer::class)->getTags(),
        );
    }

    public function testNumericConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(NumericFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(NumericFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(NumericFilterEnhancer::class)->getTags(),
        );
    }

    public function testChoiceConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(ChoiceFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(ChoiceFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(ChoiceFilterEnhancer::class)->getTags(),
        );
    }

    public function testComparisonConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(ComparisonFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(ComparisonFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(ComparisonFilterEnhancer::class)->getTags(),
        );
    }

    public function testArrayConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(ArrayFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(ArrayFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(ArrayFilterEnhancer::class)->getTags(),
        );
    }

    public function testEntityConfiguratorIsRegisteredAndTagged(): void
    {
        self::assertTrue($this->container->hasDefinition(EntityFilterEnhancer::class));
        self::assertTrue($this->container->getDefinition(EntityFilterEnhancer::class)->isAutoconfigured());

        $this->container->compile();

        self::assertArrayHasKey(
            'ea.filter_configurator',
            $this->container->getDefinition(EntityFilterEnhancer::class)->getTags(),
        );
    }

    public function testFormTypesAreRegisteredAndTagged(): void
    {
        $allFormTypes = [
            EnhancedDateTimeFilterType::class,
            EnhancedBooleanFilterType::class,
            EnhancedTextFilterType::class,
            EnhancedNumericFilterType::class,
            EnhancedChoiceFilterType::class,
            EnhancedComparisonFilterType::class,
            EnhancedArrayFilterType::class,
            EnhancedEntityFilterType::class,
        ];

        foreach ($allFormTypes as $formType) {
            self::assertTrue(
                $this->container->hasDefinition($formType),
                \sprintf('FormType %s must be registered', $formType),
            );
        }

        $this->container->compile();

        foreach ($allFormTypes as $formType) {
            self::assertArrayHasKey(
                'form.type',
                $this->container->getDefinition($formType)->getTags(),
                \sprintf('%s must be tagged form.type so Symfony Form resolves it from FQCN', $formType),
            );
        }
    }
}
