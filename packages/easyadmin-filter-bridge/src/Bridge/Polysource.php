<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;

/**
 * Static facade exposing the bridge's fluent customisation API.
 *
 * Designed so a host's `configureFilters()` / `configureFields()`
 * reads naturally:
 *
 *     use Polysource\EasyAdminFilterBridge\Bridge\Polysource as P;
 *
 *     // Per-filter customisation
 *     ->add(P::filter(BooleanFilter::new('isVisible'))
 *         ->tab('Visibility')
 *         ->group('Active state')
 *         ->chipFormatter(fn ($v) => $v ? 'Actif' : 'Inactif'))
 *
 *     // Marker mode — sequential, EA-tabs ergonomics
 *     ->add(P::tab('Visibility'))
 *     ->add(P::group('Active state'))
 *     ->add(BooleanFilter::new('isVisible'))   // inherits both
 *     ->add(BooleanFilter::new('isPublished')) // inherits both
 *     ->add(P::group('Archive state'))         // new group, same tab
 *     ->add(NotNullFilter::new('archivedAt'))  // tab=Visibility, group=Archive
 *
 *     // Field customisation (table↔chip coherence)
 *     yield P::field(BooleanField::new('isVisible'))
 *         ->chipFormatter(fn ($v) => $v ? 'Actif' : 'Inactif');
 *
 * Markers are propagated to subsequent filters by
 * {@see \Polysource\EasyAdminFilterBridge\EventListener\FilterMarkerProcessor}
 * and stripped from the FilterConfigDto before EA renders the
 * filter modal. Per-filter customisation always wins over inherited
 * marker state.
 */
final class Polysource
{
    public static function filter(FilterInterface $filter): PolysourceFilter
    {
        return PolysourceFilter::on($filter);
    }

    public static function field(FieldInterface $field): PolysourceField
    {
        return PolysourceField::on($field);
    }

    /**
     * Expansion-control field for expandable row details — yield it
     * first in `configureFields()`. Cf. {@see RowDetailField}.
     *
     * @since 1.1.0
     */
    public static function rowDetail(): RowDetailField
    {
        return RowDetailField::new();
    }

    public static function tab(string $label): PolysourceMarker
    {
        return PolysourceMarker::tab($label);
    }

    public static function group(string $label): PolysourceMarker
    {
        return PolysourceMarker::group($label);
    }

    private function __construct()
    {
    }
}
