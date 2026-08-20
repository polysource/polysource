<?php

declare(strict_types=1);

namespace Polysource\Core;

/**
 * Polysource constants — version, default page names, default tag names.
 */
final class Polysource
{
    public const VERSION = '1.1.2';

    /** Page identifiers used in {@see Field\FieldDto::$pages}. */
    public const PAGE_INDEX = 'index';
    public const PAGE_DETAIL = 'detail';
    public const PAGE_EDIT = 'edit';
    public const PAGE_NEW = 'new';

    /** Symfony DI tag names consumed by polysource/symfony-bundle. */
    public const TAG_DATA_SOURCE = 'polysource.data_source';
    public const TAG_RESOURCE = 'polysource.resource';

    // The earlier draft also defined TAG_FIELD_CONFIGURATOR, TAG_ACTION
    // and TAG_PERMISSION constants. The bundle autoconfigures the
    // string tags `polysource.action` and `polysource.row_detail_provider`
    // directly (see PolysourceExtension / RowDetailLoader); constants here
    // stay limited to the tags core itself names.

    private function __construct()
    {
    }
}
