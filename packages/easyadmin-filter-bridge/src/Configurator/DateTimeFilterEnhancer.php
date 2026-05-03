<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;

/**
 * Swap EasyAdmin's built-in `DateTimeFilter` formType for our enhanced one.
 *
 * EasyAdmin's `FilterFactory` iterates `FilterConfiguratorInterface` services
 * after each filter is created. When the filter's FQCN matches the upstream
 * `DateTimeFilter`, we mutate the DTO so the form is rendered with our
 * `EnhancedDateTimeFilterType` (presets + clear button) instead of the
 * stock `DateTimeFilterType`.
 *
 * This is the **PoC seam**: it proves that we can enhance any built-in
 * EasyAdmin filter without forking — see ADR-012.
 *
 * @see \EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory::create()
 *      for the loop that calls our `supports()` + `configure()`.
 */
final class DateTimeFilterEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return DateTimeFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(EnhancedDateTimeFilterType::class);

        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            [
                'presets' => EnhancedDateTimeFilterType::DEFAULT_PRESETS,
                'show_clear' => true,
            ],
        ));
    }
}
