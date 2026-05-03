<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ArrayFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;

/**
 * Swap EasyAdmin's built-in `ArrayFilter` formType for our enhanced one.
 *
 * Default `chip_display` is false (keeps the upstream multi-select
 * rendering) so the bridge install is non-breaking. Host apps opt in
 * per resource by passing `formTypeOptions(['chip_display' => true])`
 * in their `configureFilters()`.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 */
final class ArrayFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return ArrayFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedArrayFilterType::class);

        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            [
                'chip_display' => false,
            ],
        ));
    }
}
