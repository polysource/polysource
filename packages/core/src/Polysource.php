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

    /** Symfony DI tag names (used by polysource/symfony-bundle). */
    public const TAG_DATA_SOURCE = 'polysource.data_source';
    public const TAG_RESOURCE = 'polysource.resource';
    public const TAG_FIELD_CONFIGURATOR = 'polysource.field_configurator';
    public const TAG_ACTION = 'polysource.action';
    public const TAG_PERMISSION = 'polysource.permission';

    private function __construct() {}
}
