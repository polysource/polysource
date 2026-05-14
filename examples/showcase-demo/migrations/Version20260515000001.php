<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Showcase migration for v0.5.0 — adds the
 * `column_widths_json` column to the existing
 * `polysource_saved_views` table. Backward-compatible: nullable
 * column, so pre-v0.5.0 rows decode as an empty width map and
 * continue to behave like before.
 *
 * Hosts running their own production setup mirror this migration
 * — the canonical SQL ships in `docs/user/filter/saved-views.md`.
 */
final class Version20260515000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'v0.5.0 — add column_widths_json column to polysource_saved_views';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE polysource_saved_views ADD column_widths_json TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE polysource_saved_views DROP column_widths_json');
    }
}
