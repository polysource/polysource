<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;

/**
 * Swap EasyAdmin's built-in `NumericFilter` formType for our enhanced one.
 *
 * Defaults are conservative (step=0) so the bridge install is
 * non-breaking. Host apps opt in per resource by passing
 * `formTypeOptions(['step' => 0.01])` in their `configureFilters()`.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 */
final class NumericFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return NumericFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedNumericFilterType::class);
    }
}
