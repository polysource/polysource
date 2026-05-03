<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;

/**
 * Swap EasyAdmin's built-in `ChoiceFilter` formType for our enhanced one.
 *
 * Default `inline` is false (keeps the upstream dropdown rendering) so
 * the bridge install is non-breaking. Host apps opt in per resource by
 * passing `formTypeOptions(['inline' => true])` in their
 * `configureFilters()`, typically for short enums.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 */
final class ChoiceFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return ChoiceFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedChoiceFilterType::class);

        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            [
                'inline' => false,
            ],
        ));
    }
}
