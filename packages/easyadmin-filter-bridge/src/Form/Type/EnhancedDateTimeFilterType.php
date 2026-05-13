<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\DateTimeFilterType;

/**
 * Enhanced replacement for EasyAdmin's `DateTimeFilterType`.
 *
 * Inherits the comparison + value + value2 fields from upstream
 * (`ea_datetime_filter`). The dedicated block prefix
 * (`polysource_enhanced_datetime_filter`) lets a host application
 * override the rendering with a custom form theme without affecting
 * the built-in EasyAdmin filter.
 *
 * Earlier versions (v0.1.x) shipped `presets` (one-click date range
 * buttons) and `show_clear` (per-filter clear button) options driven
 * by a Stimulus controller. Both were removed in v0.2.0 because:
 *
 * - They required JS — host apps without a Stimulus pipeline saw
 *   inert/missing UI (violation of ADR-027 progressive enhancement).
 * - Native HTML5 date pickers + EA's built-in Reset cover the same
 *   ground without JS (ADR-028 scope discipline).
 *
 * Hosts that relied on either option should drop the call to
 * `setFormTypeOption('presets', …)` / `setFormTypeOption('show_clear', …)`
 * — both options no longer exist.
 */
final class EnhancedDateTimeFilterType extends DateTimeFilterType
{
    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_datetime_filter';
    }
}
