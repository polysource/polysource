<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use Polysource\Filter\Bridge\Contract\ChipFormatterInterface;

/**
 * Fluent decorator over an EA `FilterInterface` exposing the
 * bridge's custom-option API without polluting `formTypeOptions`.
 *
 * Implements `FilterInterface` itself so `Filters::add()` accepts
 * it transparently — the wrapped filter's behaviour (apply,
 * getAsDto, __toString) is forwarded verbatim. The proxy's only
 * job is to write to the wrapped filter's `customOptions` via
 * fluent setters.
 *
 * Usage:
 *
 *     ->add(Polysource::filter(BooleanFilter::new('isVisible'))
 *         ->tab('Visibility')
 *         ->group('Active state')
 *         ->chipFormatter(fn ($v) => $v ? 'Actif' : 'Inactif'))
 *
 * All setters delegate to `$filter->getAsDto()->setCustomOption(KEY, value)`,
 * matching EA's own internal pattern (cf. `LanguageFilter::useAlpha3Codes()`
 * which calls `$this->dto->setCustomOption(...)`).
 */
final class PolysourceFilter implements FilterInterface
{
    public static function on(FilterInterface $filter): self
    {
        return new self($filter);
    }

    private function __construct(private readonly FilterInterface $filter)
    {
    }

    public function tab(string $label): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::TAB, $label);

        return $this;
    }

    public function group(string $label): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::GROUP, $label);

        return $this;
    }

    /**
     * Sets a chip formatter that turns the filter's raw URL value
     * into a human-readable chip label.
     *
     * Two shapes accepted (cf. ADR-016):
     * - `callable(mixed $rawValue): string` — inline closure or
     *   first-class callable. Convenient for one-off cases.
     * - `ChipFormatterInterface` — service with constructor DI
     *   (TranslatorInterface, EntityManagerInterface, etc.) for
     *   reusable / testable / cross-bridge logic.
     *
     * @param callable(mixed): string|ChipFormatterInterface $formatter
     */
    public function chipFormatter(callable|ChipFormatterInterface $formatter): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::CHIP_FORMATTER, $formatter);

        return $this;
    }

    public function mapper(callable $callable): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::MAPPER, $callable);

        return $this;
    }

    public function formatter(callable $callable): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::FORMATTER, $callable);

        return $this;
    }

    /**
     * @param class-string $formTypeFqcn
     */
    public function renderer(string $formTypeFqcn): self
    {
        $this->filter->getAsDto()->setCustomOption(BridgeOptions::RENDERER, $formTypeFqcn);
        // Also update the FilterDto's actual formType so EA's
        // FiltersFormType picks it up — RENDERER customOption is
        // the *declarative* truth, FilterDto::formType is the
        // *runtime* setting EA reads. Keeping them in sync avoids
        // surprises if a host reads the customOption later.
        $this->filter->getAsDto()->setFormType($formTypeFqcn);

        return $this;
    }

    /**
     * Open extension slot for forward-compat: hosts can attach any
     * custom metadata under their own key without waiting for the
     * bridge to expose a typed setter.
     */
    public function meta(string $key, mixed $value): self
    {
        $this->filter->getAsDto()->setCustomOption($key, $value);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $this->filter->apply($queryBuilder, $filterDataDto, $fieldDto, $entityDto);
    }

    public function getAsDto(): FilterDto
    {
        return $this->filter->getAsDto();
    }

    public function __toString(): string
    {
        return (string) $this->filter;
    }
}
