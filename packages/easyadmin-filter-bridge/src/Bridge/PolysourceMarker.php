<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

/**
 * No-op `FilterInterface` implementation used as a marker in
 * marker-mode declaration:
 *
 *     ->add(Polysource::tab('Visibility'))     // a marker
 *     ->add(BooleanFilter::new('isVisible'))   // inherits "Visibility"
 *
 * Markers carry one of two custom options:
 *   - `BridgeOptions::TAB`   — set by `Polysource::tab($label)`
 *   - `BridgeOptions::GROUP` — set by `Polysource::group($label)`
 *
 * Plus `BridgeOptions::IS_MARKER = true` so the
 * {@see \Polysource\EasyAdminFilterBridge\EventListener\FilterMarkerProcessor}
 * can identify them at request time, propagate their state to
 * subsequent filters, and remove them from the FilterConfigDto
 * (they MUST NOT render as filter rows in the modal).
 *
 * `apply()` is a no-op — markers never participate in QueryBuilder
 * mutation. The processor is responsible for removing them BEFORE
 * EA's filter form is rendered, so this no-op is defense-in-depth
 * against the processor failing to run.
 *
 * Each marker carries a unique synthetic property name (UUID-ish)
 * to satisfy `FilterConfigDto::addFilter()` which keys by `(string)`
 * (FilterTrait::__toString returns the property). Reusing the same
 * property name would silently collapse markers.
 */
final class PolysourceMarker implements FilterInterface
{
    use FilterTrait;

    public static function tab(string $label): self
    {
        $instance = new self();
        $instance->setProperty(self::generatePropertyName('tab'));
        $instance->setFilterFqcn(self::class);
        $instance->setLabel($label);
        $instance->dto->setCustomOption(BridgeOptions::IS_MARKER, true);
        $instance->dto->setCustomOption(BridgeOptions::TAB, $label);

        return $instance;
    }

    public static function group(string $label): self
    {
        $instance = new self();
        $instance->setProperty(self::generatePropertyName('group'));
        $instance->setFilterFqcn(self::class);
        $instance->setLabel($label);
        $instance->dto->setCustomOption(BridgeOptions::IS_MARKER, true);
        $instance->dto->setCustomOption(BridgeOptions::GROUP, $label);

        return $instance;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        // No-op — markers never apply to the query. The processor
        // removes them from the FilterConfigDto before EA sees them,
        // but if it doesn't run for any reason we want zero effect
        // on the QueryBuilder.
    }

    public function getAsDto(): FilterDto
    {
        return $this->dto;
    }

    /**
     * Static counter would be enough but `random_bytes` keeps the
     * markers identifiable across reseeded seed data + tests.
     */
    private static function generatePropertyName(string $kind): string
    {
        return \sprintf('__polysource_marker_%s_%s', $kind, bin2hex(random_bytes(4)));
    }
}
