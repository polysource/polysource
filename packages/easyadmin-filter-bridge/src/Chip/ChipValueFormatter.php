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
use Polysource\EasyAdminFilterBridge\Doctrine\DoctrineMetadataHelper;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType;
use Polysource\Filter\Bridge\Contract\ChipFormatterInterface;
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
    /**
     * Form types that resolve to the Yes/No/Null translation chip.
     * Adding a new boolean-shaped filter is a one-line addition to
     * this map — the dispatch in {@see format()} is data-driven.
     */
    private const BOOLEAN_FORM_TYPES = [
        BooleanFilterType::class,
        EnhancedBooleanFilterType::class,
    ];

    /**
     * Form types that resolve to an entity __toString chip via the
     * Doctrine association mapping. Adding a new entity-shaped
     * filter is a one-line addition.
     */
    private const ENTITY_FORM_TYPES = [
        EntityFilterType::class,
        EnhancedEntityFilterType::class,
    ];

    /**
     * Raw URL comparison operator → EA translation key. EA already
     * ships these labels in 20+ locales AND they are the exact
     * wording of the comparison <select> in the filter modal — so
     * chips and modal stay consistent for free. The generic (numeric)
     * variants are used for the symbol operators: the raw comparison
     * alone doesn't carry the property type ('=' is "exactly" for
     * text, "is same" for dates), and the generic wording reads
     * correctly for every type.
     */
    private const EA_OPERATOR_LABEL_KEYS = [
        '=' => 'filter.label.is_equal_to',
        '!=' => 'filter.label.is_not_equal_to',
        '>' => 'filter.label.is_greater_than',
        '>=' => 'filter.label.is_greater_than_or_equal_to',
        '<' => 'filter.label.is_less_than',
        '<=' => 'filter.label.is_less_than_or_equal_to',
        'between' => 'filter.label.is_between',
        'like' => 'filter.label.contains',
        'like_all' => 'filter.label.contains_all',
        'not like' => 'filter.label.not_contains',
        'like*' => 'filter.label.starts_with',
        '*like' => 'filter.label.ends_with',
    ];

    /**
     * Operators EA has no label for (the bridge's InFilter and the
     * null family) → bridge translation keys.
     */
    private const BRIDGE_OPERATOR_LABEL_KEYS = [
        'in' => 'polysource.filter.operator.in',
        'not in' => 'polysource.filter.operator.not_in',
        'is null' => 'polysource.filter.operator.is_null',
        'is not null' => 'polysource.filter.operator.is_not_null',
    ];

    public function __construct(
        private readonly AdminContextProviderInterface $contextProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Resolves a raw comparison operator from the URL ('like',
     * 'NOT LIKE', '>='…) to a human label for the chips bar. The
     * chips-bar template upper-cases the slice comparison before
     * passing it around, so matching is case-insensitive. Unknown
     * operators fall back to the previous behaviour (lower-cased
     * verbatim) rather than guessing.
     */
    public function formatOperator(string $comparison): string
    {
        $normalized = mb_strtolower(trim($comparison));

        if (isset(self::EA_OPERATOR_LABEL_KEYS[$normalized])) {
            return $this->translator->trans(self::EA_OPERATOR_LABEL_KEYS[$normalized], [], 'EasyAdminBundle');
        }

        if (isset(self::BRIDGE_OPERATOR_LABEL_KEYS[$normalized])) {
            return $this->translator->trans(self::BRIDGE_OPERATOR_LABEL_KEYS[$normalized], [], 'PolysourceEasyAdminFilterBridge');
        }

        return $normalized;
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

        // ─── Stage 1: Filter chip_formatter (callable | ChipFormatterInterface) ───
        $formatter = $filter->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER);
        $stage1 = $this->invokeChipFormatter($formatter, $rawValue);
        if (null !== $stage1) {
            return $stage1;
        }

        // ─── Stage 2: Matching Field chip_formatter (callable | ChipFormatterInterface) ───
        $fieldFormatter = $this->lookupFieldChipFormatter($context, $property);
        $stage2 = $this->invokeChipFormatter($fieldFormatter, $rawValue);
        if (null !== $stage2) {
            return $stage2;
        }

        // ─── Stage 3: Match by FormType (covers EA built-ins +
        //              custom FilterInterface using EA form types) ───
        // Data-driven dispatch via the const maps above so hosts /
        // contributors can add a new form-type chip handler by
        // appending one entry instead of mutating a `match` block.
        $formType = $filter->getAsDto()->getFormType();
        if (\in_array($formType, self::BOOLEAN_FORM_TYPES, true)) {
            return $this->formatBoolean($rawValue);
        }
        if (\in_array($formType, self::ENTITY_FORM_TYPES, true)) {
            return $this->formatEntity($context, $property, $rawValue);
        }
        if (NotNullFilterType::class === $formType) {
            return $this->formatNotNull($filter, $rawValue);
        }

        // ─── Stage 4: Auto-detect Doctrine association ───
        if ($this->isDoctrineAssociation($context, $property)) {
            return $this->formatEntity($context, $property, $rawValue);
        }

        // ─── Stage 5: Default stringify ───
        return $this->stringify($rawValue);
    }

    /**
     * Resolves a NotNullFilter tri-state raw value ('' | 'not_null' |
     * 'null') to the human label declared in the filter's `labels`
     * form-type option (defaults: Any / Has value / Empty).
     *
     * Lives HERE, as part of the stage-3 form-type dispatch, and NOT
     * in the chips-bar template: resolving it in Twig used to replace
     * the raw value BEFORE `polysource_chip_value` ran, so stage-1/2
     * chipFormatters never saw NotNull values — a violation of the
     * "host formatter always wins" contract (2026-08 host regression).
     */
    private function formatNotNull(FilterInterface $filter, mixed $rawValue): string
    {
        /** @var array{any?: string, not_null?: string, null?: string} $labels */
        $labels = $filter->getAsDto()->getFormTypeOptions()['labels'] ?? [];

        $label = match ($rawValue) {
            NotNullFilterType::VALUE_NOT_NULL => $labels['not_null'] ?? 'polysource.filter.not_null.has_value',
            NotNullFilterType::VALUE_NULL => $labels['null'] ?? 'polysource.filter.not_null.empty',
            NotNullFilterType::VALUE_ANY, null => $labels['any'] ?? 'polysource.filter.not_null.any',
            default => null,
        };

        if (null === $label) {
            return $this->stringify($rawValue);
        }

        // Same contract as the form widget (choice_translation_domain):
        // default labels are translation keys resolved in the bridge's
        // domain; host-provided plain strings are echoed verbatim.
        return $this->translator->trans($label, [], 'PolysourceEasyAdminFilterBridge');
    }

    /**
     * Invokes a chip formatter — either a callable or an
     * implementation of {@see ChipFormatterInterface} (cf. ADR-016).
     * Returns the formatted label, or null when no formatter was
     * supplied (caller falls through to the next stage). Non-string
     * results from a callable are stringified defensively to keep
     * the contract honest.
     */
    private function invokeChipFormatter(mixed $formatter, mixed $rawValue): ?string
    {
        if ($formatter instanceof ChipFormatterInterface) {
            return $formatter->format($rawValue);
        }

        if (\is_callable($formatter)) {
            $result = $formatter($rawValue);

            return \is_string($result) ? $result : $this->stringify($rawValue);
        }

        return null;
    }

    /**
     * @param AdminContextInterface<object> $context
     */
    private function lookupFieldChipFormatter(AdminContextInterface $context, string $property): mixed
    {
        $fields = $context->getEntity()->getFields();
        if (null === $fields) {
            return null;
        }

        $field = $fields->getByProperty($property);
        if (null === $field) {
            return null;
        }

        $value = $field->getCustomOption(BridgeOptions::CHIP_FORMATTER);

        // Pass through callables and ChipFormatterInterface
        // implementations — the dispatcher upstream
        // ({@see invokeChipFormatter}) routes both shapes.
        if ($value instanceof ChipFormatterInterface || \is_callable($value)) {
            return $value;
        }

        return null;
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

        // Doctrine ORM 2.x exposes the mapping as an array, 3.x as
        // an AssociationMapping object. DoctrineMetadataHelper hides
        // the shape detection — extracted in v0.9.0 so the cross-
        // version trivia lives in one well-named class instead of
        // being inlined here.
        $targetClass = DoctrineMetadataHelper::extractTargetEntity(
            $metadata->getAssociationMapping($property),
        );

        if (null === $targetClass) {
            return $this->stringify($rawValue);
        }

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
