<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Canonical filter operators that adapters translate into their native
 * query language (Doctrine DQL, Redis SCAN match, HTTP query string,
 * Meilisearch filter syntax, etc.).
 *
 * Backed by string for two reasons:
 *
 * 1. URL serialization in `?filter[name][op]=...` round-trips through
 *    `FilterOperator::tryFrom($string)` — invalid operators fall back to
 *    {@see self::Eq} at the caller's discretion.
 * 2. Session persistence and saved-view storage stay JSON-stable.
 *
 * Closes ADR-011 item A4. Prior to v0.7 this was a free-form
 * `string` field on {@see FilterCriterion}, with the docblock as the
 * only authority on the valid set — error-prone and unverifiable by
 * PHPStan / IDE.
 *
 * @since 0.7.0
 */
enum FilterOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Like = 'like';
    case In = 'in';
    case Nin = 'nin';
    case Between = 'between';
    case IsNull = 'null';
    case IsNotNull = 'notnull';
}
