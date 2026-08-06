<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Configurator\ArrayFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\BooleanFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ChoiceFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ComparisonFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\DateTimeFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\EntityFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\NumericFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\TextFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The 8 enhanced FormTypes + their 8 FilterConfigurators — the
 * bridge's core. Auto-tagging is handled by EasyAdmin's own
 * `registerForAutoconfiguration(FilterConfiguratorInterface::class)`
 * so the services are registered with autoconfiguration enabled and
 * EasyAdmin's FilterFactory picks them up. Form types tagged
 * `form.type` are auto-discovered by Symfony Form likewise.
 *
 * Always on — the extension's EA gate (C1) already guarantees
 * EasyAdmin is present when any loader runs.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class EnhancerLoader implements FeatureLoaderInterface
{
    private const SERVICES = [
        EnhancedDateTimeFilterType::class,
        EnhancedBooleanFilterType::class,
        EnhancedTextFilterType::class,
        EnhancedNumericFilterType::class,
        EnhancedChoiceFilterType::class,
        EnhancedComparisonFilterType::class,
        EnhancedArrayFilterType::class,
        EnhancedEntityFilterType::class,
        DateTimeFilterEnhancer::class,
        BooleanFilterEnhancer::class,
        TextFilterEnhancer::class,
        NumericFilterEnhancer::class,
        ChoiceFilterEnhancer::class,
        ComparisonFilterEnhancer::class,
        ArrayFilterEnhancer::class,
        EntityFilterEnhancer::class,
    ];

    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        foreach (self::SERVICES as $class) {
            $container
                ->register($class)
                ->setAutoconfigured(true)
                ->setAutowired(true)
            ;
        }
    }
}
