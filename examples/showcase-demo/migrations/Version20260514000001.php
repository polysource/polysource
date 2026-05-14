<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.3.0 — adds the
 * `polysource_column_preferences` table introduced by
 * `polysource/filter`'s new `ColumnPreference` slice.
 *
 * The base entity is auto-mapped by the filter package's DI
 * `prepend()` (cf. `Polysource\Filter\DependencyInjection\PolysourceFilterExtension::prepend()`),
 * which means Doctrine will try to TRUNCATE this table during
 * `doctrine:fixtures:load --purge-with-truncate`. Without this
 * migration the truncate fails with "relation does not exist"
 * and every E2E test downstream fails uniformly.
 *
 * Schema mirrors the entity's mapping exactly — composite primary
 * key on (owner_id, resource_name), one row per (user, resource)
 * pair, JSON-encoded hidden-columns list.
 */
final class Version20260514000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.3.0 — add polysource_column_preferences table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE polysource_column_preferences (owner_id VARCHAR(128) NOT NULL, resource_name VARCHAR(128) NOT NULL, hidden_columns_json TEXT NOT NULL, PRIMARY KEY (owner_id, resource_name))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE polysource_column_preferences');
    }
}
