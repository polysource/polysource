<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.5.0 — adds the
 * `polysource_bulk_action_history` table introduced by
 * `polysource/filter`'s new BulkActionHistory slice.
 *
 * The entity is auto-mapped by the filter package's DI
 * `prepend()`, which means Doctrine will try to TRUNCATE this
 * table during `doctrine:fixtures:load --purge-with-truncate`.
 * Without this migration the truncate fails with
 * "relation does not exist" and every E2E test downstream fails
 * uniformly — same pattern as Version20260514000001.
 */
final class Version20260515000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.5.0 — add polysource_bulk_action_history table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE polysource_bulk_action_history ('
            . 'id VARCHAR(64) NOT NULL, '
            . 'owner_id VARCHAR(128) NOT NULL, '
            . 'resource_name VARCHAR(128) NOT NULL, '
            . 'action_name VARCHAR(128) NOT NULL, '
            . 'affected_count INTEGER NOT NULL, '
            . 'occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            . 'metadata_json TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (id)'
            . ')',
        );
        $this->addSql('CREATE INDEX polysource_bulk_action_history_resource_idx ON polysource_bulk_action_history (resource_name)');
        $this->addSql('CREATE INDEX polysource_bulk_action_history_owner_idx ON polysource_bulk_action_history (owner_id)');
        $this->addSql('CREATE INDEX polysource_bulk_action_history_occurred_idx ON polysource_bulk_action_history (occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE polysource_bulk_action_history');
    }
}
