<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Model;

/**
 * Visibility scope of a saved view.
 *
 * Three levels — anything finer-grained (per-team-and-role,
 * organisation hierarchies, etc.) is host territory and composed via
 * {@see \Polysource\Filter\SavedView\Security\SavedViewVoter}, not
 * baked into this enum.
 *
 * Cf. ADR-019 §3.
 *
 * @since 0.1.0
 */
enum SavedViewScope: string
{
    /** Visible only to its owner. */
    case PRIVATE = 'private';

    /** Visible to every user sharing the saved view's `teamId`. */
    case TEAM = 'team';

    /** Visible to every authenticated user. */
    case PUBLIC = 'public';
}
