<?php

declare(strict_types=1);

namespace Polysource\Core;

/**
 * Polysource constants — version, default page names, default tag names.
 */
final class Polysource
{
    public const VERSION = '0.1.0-dev';

    /** Page identifiers used in {@see Field\FieldDto::$pages}. */
    public const PAGE_INDEX = 'index';
    public const PAGE_DETAIL = 'detail';
    public const PAGE_EDIT = 'edit';
    public const PAGE_NEW = 'new';

    /** Symfony DI tag names consumed by polysource/symfony-bundle. */
    public const TAG_DATA_SOURCE = 'polysource.data_source';
    public const TAG_RESOURCE = 'polysource.resource';

    // The earlier draft also defined TAG_FIELD_CONFIGURATOR, TAG_ACTION
    // and TAG_PERMISSION, but no Polysource code currently registers
    // services under those tags. They were removed in Phase 7.x to
    // avoid the YAGNI smell — re-introduce them when (and if) the
    // bundle starts auto-discovering tagged services for those types.

    private function __construct()
    {
    }
}
