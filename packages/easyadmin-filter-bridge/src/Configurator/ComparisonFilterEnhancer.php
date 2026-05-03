<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;

/**
 * Swap EasyAdmin's built-in `ComparisonFilter` formType for our enhanced one.
 *
 * Default `comparisons` is `[]` (expose every operator) so the bridge
 * install is non-breaking. Host apps opt in per resource by passing
 * `formTypeOptions(['comparisons' => ['=', '!=']])` in their
 * `configureFilters()` to limit the operator dropdown.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 */
final class ComparisonFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return ComparisonFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedComparisonFilterType::class);
    }
}
