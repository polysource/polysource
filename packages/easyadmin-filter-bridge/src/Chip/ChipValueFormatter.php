<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Chip;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Formats raw filter values from the URL into human-readable
 * chip text by inspecting EA's `FilterDto` type.
 *
 * The internal pattern's formatter pipeline (in `polysource/filter`)
 * is designed for non-EA hosts that consume `FilterDefinition`s.
 * The bridge consumes EA's `FilterDto`s (with EA's filter FQCNs)
 * directly — wiring the polysource pipeline through the bridge
 * would mean a side-translation step (FQCN → polysource name)
 * for every chip render, with no DX gain. So the bridge ships
 * its own EA-aware formatter that solves the two end-user pain
 * points:
 *
 * - **Boolean**: `1` / `0` / `''` resolved to translated
 *   "Yes" / "No" / "Empty" (via the `EasyAdminBundle` translation
 *   domain so locale comes for free).
 * - **Entity**: scalar PK resolved via Doctrine to the entity's
 *   `__toString()` — `Category : 2` becomes `Category : Electronics`.
 *
 * All other filter shapes (text, numeric, datetime, choice, …)
 * fall through to a defensive `stringify` that joins arrays with
 * commas and casts scalars verbatim.
 *
 * Hosts wanting custom resolution decorate this service or
 * register a competing Twig extension shadowing
 * `polysource_chip_value`.
 */
final class ChipValueFormatter
{
    public function __construct(
        private readonly AdminContextProviderInterface $contextProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function format(string $property, mixed $rawValue): string
    {
        $context = $this->contextProvider->getContext();
        if (null === $context) {
            return $this->stringify($rawValue);
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            return $this->stringify($rawValue);
        }

        $filter = $crud->getFiltersConfig()->getFilter($property);
        if (!$filter instanceof FilterInterface) {
            return $this->stringify($rawValue);
        }

        $fqcn = $filter->getAsDto()->getFqcn();

        return match ($fqcn) {
            BooleanFilter::class => $this->formatBoolean($rawValue),
            EntityFilter::class => $this->formatEntity($context, $property, $rawValue),
            default => $this->stringify($rawValue),
        };
    }

    private function formatBoolean(mixed $rawValue): string
    {
        if (true === $rawValue || '1' === $rawValue || 1 === $rawValue) {
            return $this->translator->trans('label.true', [], 'EasyAdminBundle');
        }

        if (false === $rawValue || '0' === $rawValue || 0 === $rawValue) {
            return $this->translator->trans('label.false', [], 'EasyAdminBundle');
        }

        // null / empty string / EnhancedBooleanFilterType's
        // "include_null" choice (CHOICE_NULL = 'null').
        return $this->translator->trans('label.null', [], 'EasyAdminBundle');
    }

    /**
     * @param AdminContextInterface<object> $context
     */
    private function formatEntity(AdminContextInterface $context, string $property, mixed $rawValue): string
    {
        if (null === $rawValue || '' === $rawValue) {
            return '';
        }

        // Multi-select EntityFilter ships value as a list of PKs.
        if (\is_array($rawValue)) {
            $resolved = array_map(
                fn (mixed $pk): string => $this->formatEntity($context, $property, $pk),
                $rawValue,
            );

            return implode(', ', array_filter($resolved, static fn (string $s): bool => '' !== $s));
        }

        // Resolve metadata via the EntityManager rather than
        // EntityDto::getDoctrineMetadata() — the latter is wired
        // lazily by EA and may not be initialised at the point
        // the chips bar renders (before EA's own pre-render hooks
        // populate it). EM's classMetadataFactory always works.
        $entityFqcn = $context->getEntity()->getFqcn();
        if (!class_exists($entityFqcn)) {
            return $this->stringify($rawValue);
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($entityFqcn);
        } catch (Throwable) {
            return $this->stringify($rawValue);
        }

        if (!$metadata->hasAssociation($property)) {
            return $this->stringify($rawValue);
        }

        $mapping = $metadata->getAssociationMapping($property);
        $targetClass = $mapping->targetEntity;

        try {
            $entity = $this->entityManager->find($targetClass, $rawValue);
        } catch (Throwable) {
            return $this->stringify($rawValue);
        }

        if (!$entity instanceof \Stringable) {
            return $this->stringify($rawValue);
        }

        return (string) $entity;
    }

    private function stringify(mixed $value): string
    {
        if (\is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (\is_scalar($item) || null === $item) {
                    $parts[] = (string) $item;
                }
            }

            return implode(', ', $parts);
        }

        if (\is_scalar($value) || null === $value) {
            return (string) $value;
        }

        return '';
    }
}
