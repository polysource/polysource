<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260506093802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE polysource_audit_log (id VARCHAR(36) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_id VARCHAR(120) NOT NULL, actor_label VARCHAR(120) DEFAULT NULL, resource_name VARCHAR(120) NOT NULL, action_name VARCHAR(120) NOT NULL, record_ids_json TEXT NOT NULL, outcome VARCHAR(16) NOT NULL, message TEXT DEFAULT NULL, duration_ms INT NOT NULL, context_json TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX polysource_audit_log_occurred_idx ON polysource_audit_log (occurred_at)');
        $this->addSql('CREATE INDEX polysource_audit_log_actor_resource_idx ON polysource_audit_log (actor_id, resource_name)');
        $this->addSql('CREATE INDEX polysource_audit_log_resource_action_idx ON polysource_audit_log (resource_name, action_name)');
        $this->addSql('CREATE TABLE polysource_bulk_jobs (id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, resource_name VARCHAR(120) NOT NULL, action_name VARCHAR(120) NOT NULL, actor_id VARCHAR(120) NOT NULL, record_ids_json TEXT NOT NULL, status VARCHAR(16) NOT NULL, processed_count INT NOT NULL, failed_count INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX polysource_bulk_jobs_created_idx ON polysource_bulk_jobs (created_at)');
        $this->addSql('CREATE INDEX polysource_bulk_jobs_actor_idx ON polysource_bulk_jobs (actor_id, created_at)');
        $this->addSql('CREATE INDEX polysource_bulk_jobs_status_idx ON polysource_bulk_jobs (status)');
        $this->addSql('CREATE TABLE polysource_saved_views (id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, resource_name VARCHAR(128) NOT NULL, owner_id VARCHAR(128) NOT NULL, scope VARCHAR(16) NOT NULL, filters_json TEXT NOT NULL, columns_json TEXT NOT NULL, sort_json TEXT NOT NULL, page_size INT DEFAULT NULL, team_id VARCHAR(128) DEFAULT NULL, is_default BOOLEAN NOT NULL, role_as_default VARCHAR(64) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX polysource_saved_views_resource_idx ON polysource_saved_views (resource_name)');
        $this->addSql('CREATE INDEX polysource_saved_views_owner_idx ON polysource_saved_views (owner_id)');
        $this->addSql('CREATE TABLE shopco_customer (id UUID NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, phone VARCHAR(30) DEFAULT NULL, address_line VARCHAR(200) DEFAULT NULL, city VARCHAR(80) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, country VARCHAR(2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shopco_customer_country ON shopco_customer (country)');
        $this->addSql('CREATE INDEX idx_shopco_customer_created_at ON shopco_customer (created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopco_customer_email ON shopco_customer (email)');
        $this->addSql('CREATE TABLE shopco_order (id UUID NOT NULL, reference VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, total_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, shipping_address VARCHAR(240) DEFAULT NULL, payment_transaction_id VARCHAR(60) DEFAULT NULL, tracking_number VARCHAR(40) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, shipped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, customer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shopco_order_status ON shopco_order (status)');
        $this->addSql('CREATE INDEX idx_shopco_order_created_at ON shopco_order (created_at)');
        $this->addSql('CREATE INDEX idx_shopco_order_customer ON shopco_order (customer_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopco_order_reference ON shopco_order (reference)');
        $this->addSql('CREATE TABLE shopco_order_item (id UUID NOT NULL, quantity INT NOT NULL, unit_price_cents INT NOT NULL, order_id UUID NOT NULL, product_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FB9358CD4584665A ON shopco_order_item (product_id)');
        $this->addSql('CREATE INDEX idx_shopco_order_item_order ON shopco_order_item (order_id)');
        $this->addSql('CREATE TABLE shopco_product (id UUID NOT NULL, sku VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, description TEXT NOT NULL, price_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, stock INT NOT NULL, status VARCHAR(16) NOT NULL, category VARCHAR(60) NOT NULL, photo_path VARCHAR(240) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shopco_product_status ON shopco_product (status)');
        $this->addSql('CREATE INDEX idx_shopco_product_category ON shopco_product (category)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopco_product_sku ON shopco_product (sku)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopco_product_slug ON shopco_product (slug)');
        $this->addSql('CREATE TABLE shopco_refund (id UUID NOT NULL, amount_cents INT NOT NULL, reason VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shopco_refund_status ON shopco_refund (status)');
        $this->addSql('CREATE INDEX idx_shopco_refund_order ON shopco_refund (order_id)');
        $this->addSql('CREATE INDEX idx_shopco_refund_created_at ON shopco_refund (created_at)');
        $this->addSql('CREATE TABLE shopco_user (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopco_user_email ON shopco_user (email)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE shopco_order ADD CONSTRAINT FK_FF103BCD9395C3F3 FOREIGN KEY (customer_id) REFERENCES shopco_customer (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopco_order_item ADD CONSTRAINT FK_FB9358CD8D9F6D38 FOREIGN KEY (order_id) REFERENCES shopco_order (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopco_order_item ADD CONSTRAINT FK_FB9358CD4584665A FOREIGN KEY (product_id) REFERENCES shopco_product (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopco_refund ADD CONSTRAINT FK_4027888B8D9F6D38 FOREIGN KEY (order_id) REFERENCES shopco_order (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shopco_order DROP CONSTRAINT FK_FF103BCD9395C3F3');
        $this->addSql('ALTER TABLE shopco_order_item DROP CONSTRAINT FK_FB9358CD8D9F6D38');
        $this->addSql('ALTER TABLE shopco_order_item DROP CONSTRAINT FK_FB9358CD4584665A');
        $this->addSql('ALTER TABLE shopco_refund DROP CONSTRAINT FK_4027888B8D9F6D38');
        $this->addSql('DROP TABLE polysource_audit_log');
        $this->addSql('DROP TABLE polysource_bulk_jobs');
        $this->addSql('DROP TABLE polysource_saved_views');
        $this->addSql('DROP TABLE shopco_customer');
        $this->addSql('DROP TABLE shopco_order');
        $this->addSql('DROP TABLE shopco_order_item');
        $this->addSql('DROP TABLE shopco_product');
        $this->addSql('DROP TABLE shopco_refund');
        $this->addSql('DROP TABLE shopco_user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
