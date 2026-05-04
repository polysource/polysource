<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;

/**
 * Mirrors the host-declared `polysource_group` formTypeOption into
 * the `FilterDto`'s customOptions so the
 * `crud/filters.html.twig` Twig override can read it back when
 * rendering the filter form.
 *
 * Hosts declare the group via the standard EA filter API:
 *
 *     yield ChoiceFilter::new('status')
 *         ->setFormTypeOption('polysource_group', 'Status');
 *
 * The companion {@see \Polysource\EasyAdminFilterBridge\Form\Extension\PolysourceFilterFormTypeExtension}
 * widens every FormType's `OptionsResolver` to accept
 * `polysource_group` so EA's built-in filter form types don't crash
 * on the unknown option. This Configurator simply propagates the
 * value to `customOption` so the Twig override can do
 * `ea().crud.filtersConfig.getFilter(name).asDto.customOption('polysource_group')`.
 *
 * Always-supports: idempotent, runs after every filter.
 * `configure()` is a no-op when the option isn't set or isn't a
 * non-empty string.
 */
final class GroupCarrierConfigurator implements FilterConfiguratorInterface
{
    public const OPTION_NAME = 'polysource_group';

    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return true;
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $group = $filterDto->getFormTypeOption(self::OPTION_NAME);

        // Only carry non-empty string groups; null/empty/non-string
        // is treated as "no group" so hosts can dynamically opt out.
        if (\is_string($group) && '' !== $group) {
            $filterDto->setCustomOption(self::OPTION_NAME, $group);
        }
    }
}
