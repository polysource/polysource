<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection;

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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension for `polysource/easyadmin-filter-bridge`.
 *
 * Registers the `FilterConfiguratorInterface` services and the enhanced
 * Symfony FormTypes. Auto-tagging is handled by EasyAdmin's own
 * `registerForAutoconfiguration(FilterConfiguratorInterface::class)`
 * (cf. `EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension`),
 * so we just register the services with autoconfiguration enabled and
 * EasyAdmin picks them up.
 *
 * Form types tagged `form.type` are auto-discovered by Symfony Form when
 * autoconfiguration is on (default).
 */
final class PolysourceEasyAdminFilterBridgeExtension extends Extension
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container
            ->register(EnhancedDateTimeFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedBooleanFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedTextFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedNumericFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedChoiceFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedComparisonFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedArrayFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedEntityFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(DateTimeFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(BooleanFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(TextFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(NumericFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ChoiceFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ComparisonFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ArrayFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EntityFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;
    }

    public function getAlias(): string
    {
        return 'polysource_easyadmin_filter_bridge';
    }
}
