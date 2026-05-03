<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;

/**
 * Swap EasyAdmin's built-in `BooleanFilter` formType for our enhanced one.
 *
 * Same pattern as {@see DateTimeFilterEnhancer} — proves the bridge scales
 * across filter types without any EasyAdmin modification. The default
 * `include_null` is false (so the behaviour is identical to upstream out
 * of the box); host apps opt in per resource by passing
 * `formTypeOptions(['include_null' => true])` in their `configureFilters()`.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 *      for the loop that calls our `supports()` + `configure()`.
 */
final class BooleanFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return BooleanFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedBooleanFilterType::class);

        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            [
                'include_null' => false,
            ],
        ));
    }
}
