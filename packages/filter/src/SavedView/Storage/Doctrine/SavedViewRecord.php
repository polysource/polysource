<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Storage\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_saved_views` table.
 *
 * Kept separate from the {@see \Polysource\Filter\SavedView\Model\SavedView}
 * VO on purpose — VO carries domain semantics (immutability,
 * invariants), the record carries persistence concerns (column
 * mapping, JSON serialisation of complex fields).
 *
 * Conversion VO ↔ record happens in
 * {@see \Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage}.
 *
 * Hosts must run a Doctrine migration to create the table; the
 * recommended SQL ships in `docs/user/filter/saved-views.md`.
 * Polysource does not auto-migrate (cf. ADR-019 §"Conséquences").
 *
 * @since 0.1.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_saved_views')]
#[ORM\Index(name: 'polysource_saved_views_resource_idx', columns: ['resource_name'])]
#[ORM\Index(name: 'polysource_saved_views_owner_idx', columns: ['owner_id'])]
class SavedViewRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    public string $id;

    #[ORM\Column(type: 'string', length: 255)]
    public string $name;

    #[ORM\Column(name: 'resource_name', type: 'string', length: 128)]
    public string $resourceName;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 128)]
    public string $ownerId;

    #[ORM\Column(type: 'string', length: 16)]
    public string $scope;

    /** Serialised FilterCollection criteria — JSON list of {property, operator, values}. */
    #[ORM\Column(name: 'filters_json', type: 'text')]
    public string $filtersJson;

    /** JSON list<string> of selected columns. */
    #[ORM\Column(name: 'columns_json', type: 'text')]
    public string $columnsJson;

    /** JSON map<string, "asc"|"desc">. */
    #[ORM\Column(name: 'sort_json', type: 'text')]
    public string $sortJson;

    #[ORM\Column(name: 'page_size', type: 'integer', nullable: true)]
    public ?int $pageSize = null;

    #[ORM\Column(name: 'team_id', type: 'string', length: 128, nullable: true)]
    public ?string $teamId = null;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    public bool $isDefault = false;

    #[ORM\Column(name: 'role_as_default', type: 'string', length: 64, nullable: true)]
    public ?string $roleAsDefault = null;

    /**
     * JSON map<string, int> — column property → pixel width override.
     * Nullable so existing rows pre-v0.5.0 stay valid without backfill;
     * the storage decodes a null/missing value as an empty map.
     *
     * @since 0.5.0
     */
    #[ORM\Column(name: 'column_widths_json', type: 'text', nullable: true)]
    public ?string $columnWidthsJson = null;
}
