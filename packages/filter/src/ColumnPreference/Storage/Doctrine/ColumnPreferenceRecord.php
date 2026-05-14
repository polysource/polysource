<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Storage\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_column_preferences` table.
 *
 * Composite primary key on (owner_id, resource_name) — there is
 * exactly one row per user per resource. No surrogate id; the
 * natural key IS the identity.
 *
 * Hosts must run a Doctrine migration to create the table; the
 * recommended SQL ships in `docs/user/filter/column-preferences.md`.
 * Polysource does not auto-migrate (cf. ADR-019 §"Conséquences",
 * same rationale as SavedView).
 *
 * @since 0.3.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_column_preferences')]
class ColumnPreferenceRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'owner_id', type: 'string', length: 128)]
    public string $ownerId;

    #[ORM\Id]
    #[ORM\Column(name: 'resource_name', type: 'string', length: 128)]
    public string $resourceName;

    /** JSON list<string> of hidden property names. */
    #[ORM\Column(name: 'hidden_columns_json', type: 'text')]
    public string $hiddenColumnsJson;
}
