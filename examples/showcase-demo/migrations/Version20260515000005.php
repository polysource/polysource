<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.5.0 — adds the
 * `polysource_filter_url_tokens` table for the
 * `polysource/filter` short-URL slice.
 */
final class Version20260515000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.5.0 — add polysource_filter_url_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE polysource_filter_url_tokens ('
            . 'token VARCHAR(32) NOT NULL, '
            . 'resource_name VARCHAR(128) NOT NULL, '
            . 'filters_slice_json TEXT NOT NULL, '
            . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            . 'PRIMARY KEY (token)'
            . ')',
        );
        $this->addSql('CREATE INDEX polysource_filter_url_tokens_resource_idx ON polysource_filter_url_tokens (resource_name)');
        $this->addSql('CREATE INDEX polysource_filter_url_tokens_created_idx ON polysource_filter_url_tokens (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE polysource_filter_url_tokens');
    }
}
