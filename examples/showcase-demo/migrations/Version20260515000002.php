<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.5.0 — adds the
 * `column_order_json` column to the existing
 * `polysource_column_preferences` table.
 * Backward-compatible: nullable column, so pre-v0.5.0 rows
 * decode as "no order override" and the host's default
 * ordering keeps applying.
 */
final class Version20260515000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.5.0 — add column_order_json column to polysource_column_preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE polysource_column_preferences ADD column_order_json TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE polysource_column_preferences DROP column_order_json');
    }
}
