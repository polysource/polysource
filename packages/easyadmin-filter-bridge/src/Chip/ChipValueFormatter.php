<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Chip;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Stringable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Resolves a raw filter value (from the URL) to a human-readable
 * chip label via a 5-stage routing chain — ordered from most
 * specific (host's per-filter callable) to most generic (default
 * stringify).
 *
 * The chain:
 *
 *   1. Filter `customOption(CHIP_FORMATTER)` callable — host's
 *      per-filter override declared via
 *      `Polysource::filter($f)->chipFormatter(fn ($v) => ...)`.
 *   2. Matching Field `customOption(CHIP_FORMATTER)` callable —
 *      host's per-field override declared via
 *      `Polysource::field($f)->chipFormatter(fn ($v) => ...)`.
 *      Looked up by property name on the EntityDto's FieldCollection.
 *      ENABLES TABLE↔CHIP COHERENCE: the same callable formats
 *      the column AND the chip with one declaration.
 *   3. Match by `FilterDto::getFormType()` — handles every filter
 *      that uses BooleanFilterType / EntityFilterType regardless
 *      of the FilterInterface implementation. Covers EA built-ins
 *      AND host customs (e.g. a `FreewheelCreativeIsSentFilter`
 *      that sets `setFormType(BooleanFilterType::class)` gets the
 *      Yes/No translation for free).
 *   4. Auto-detect Doctrine association on the property — covers
 *      hosts filtering on an association property without using
 *      `EntityFilter` explicitly (e.g. a custom
 *      `AssociationByIdFilter`).
 *   5. Default stringify — defensive fallback. Scalars cast
 *      verbatim, arrays joined with commas, objects emit empty
 *      string.
 *
 * Hosts can shadow the entire chain by registering a competing
 * Twig extension exposing `polysource_chip_value` (Twig's last-
 * registered-wins precedence), or by service-decorating
 * {@see \Polysource\EasyAdminFilterBridge\Twig\Extension\ChipExtension}.
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

        // ─── Stage 1: Filter chip_formatter callable ───
        $callable = $filter->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER);
        if (\is_callable($callable)) {
            $result = $callable($rawValue);

            return \is_string($result) ? $result : $this->stringify($rawValue);
        }

        // ─── Stage 2: Matching Field chip_formatter callable ───
        $fieldCallable = $this->lookupFieldChipFormatter($context, $property);
        if (null !== $fieldCallable) {
            $result = $fieldCallable($rawValue);

            return \is_string($result) ? $result : $this->stringify($rawValue);
        }

        // ─── Stage 3: Match by FormType (covers EA built-ins +
        //              custom FilterInterface using EA form types) ───
        $formType = $filter->getAsDto()->getFormType();
        if (\in_array($formType, [BooleanFilterType::class, EnhancedBooleanFilterType::class], true)) {
            return $this->formatBoolean($rawValue);
        }
        if (\in_array($formType, [EntityFilterType::class, EnhancedEntityFilterType::class], true)) {
            return $this->formatEntity($context, $property, $rawValue);
        }

        // ─── Stage 4: Auto-detect Doctrine association ───
        if ($this->isDoctrineAssociation($context, $property)) {
            return $this->formatEntity($context, $property, $rawValue);
        }

        // ─── Stage 5: Default stringify ───
        return $this->stringify($rawValue);
    }

    /**
     * @param AdminContextInterface<object> $context
     */
    private function lookupFieldChipFormatter(AdminContextInterface $context, string $property): ?callable
    {
        $fields = $context->getEntity()->getFields();
        if (null === $fields) {
            return null;
        }

        $field = $fields->getByProperty($property);
        if (null === $field) {
            return null;
        }

        $callable = $field->getCustomOption(BridgeOptions::CHIP_FORMATTER);

        return \is_callable($callable) ? $callable : null;
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

        if (!$entity instanceof Stringable) {
            return $this->stringify($rawValue);
        }

        return (string) $entity;
    }

    /**
     * @param AdminContextInterface<object> $context
     */
    private function isDoctrineAssociation(AdminContextInterface $context, string $property): bool
    {
        $entityFqcn = $context->getEntity()->getFqcn();
        if (!class_exists($entityFqcn)) {
            return false;
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($entityFqcn);
        } catch (Throwable) {
            return false;
        }

        return $metadata->hasAssociation($property);
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
