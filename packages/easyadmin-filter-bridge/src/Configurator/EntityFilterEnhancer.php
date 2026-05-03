<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;

/**
 * Swap EasyAdmin's built-in `EntityFilter` formType for our enhanced one.
 *
 * Default `placeholder` is null (keeps the upstream behaviour) so the
 * bridge install is non-breaking. Host apps opt in per resource by
 * passing `formTypeOptions(['placeholder' => 'Pick a category…'])` in
 * their `configureFilters()`.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 */
final class EntityFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return EntityFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedEntityFilterType::class);

        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            [
                'placeholder' => null,
            ],
        ));
    }
}
