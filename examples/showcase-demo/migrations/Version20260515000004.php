<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.5.0 — adds the
 * `polysource_recent_records` table for the
 * `polysource/filter` recently-viewed-records slice.
 *
 * Composite primary key on (owner_id, resource_name, record_id)
 * so repeated views upsert rather than append. Index on
 * `viewed_at` for the ORDER BY DESC LIMIT N query that powers
 * the "Recently viewed" widget.
 */
final class Version20260515000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.5.0 — add polysource_recent_records table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE polysource_recent_records ('
            . 'owner_id VARCHAR(128) NOT NULL, '
            . 'resource_name VARCHAR(128) NOT NULL, '
            . 'record_id VARCHAR(128) NOT NULL, '
            . 'viewed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            . 'label VARCHAR(255) DEFAULT NULL, '
            . 'PRIMARY KEY (owner_id, resource_name, record_id)'
            . ')',
        );
        $this->addSql('CREATE INDEX polysource_recent_records_viewed_idx ON polysource_recent_records (viewed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE polysource_recent_records');
    }
}
