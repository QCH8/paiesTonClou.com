<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219102435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(100) NOT NULL, currency VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price_htsnapshot INT NOT NULL COMMENT \'Amount in cents\', vat_rate_snapshot DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(180) NOT NULL, status VARCHAR(100) NOT NULL, customer_email VARCHAR(180) NOT NULL, currency VARCHAR(100) NOT NULL, total_ht INT NOT NULL COMMENT \'Amount in cents\', total_vat INT NOT NULL COMMENT \'Amount in cents\', total_ttc INT NOT NULL COMMENT \'Amount in cents\', created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, shipping_fullname VARCHAR(180) NOT NULL, shipping_line1 VARCHAR(255) NOT NULL, shipping_line2 VARCHAR(255) DEFAULT NULL, shipping_city VARCHAR(255) NOT NULL, shipping_postal_code VARCHAR(255) NOT NULL, shipping_country VARCHAR(255) NOT NULL, shipping_phone VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_F529939896901F54 (number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price_htsnapshot INT NOT NULL COMMENT \'Amount in cents\', vat_rate_snapshot DOUBLE PRECISION NOT NULL, name_snapshot VARCHAR(255) NOT NULL, stock_keeping_unit_snapshot VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, stripe_checkout_session_id VARCHAR(180) NOT NULL, stripe_payment_intent_id VARCHAR(180) DEFAULT NULL, amount_ttc INT NOT NULL COMMENT \'Amount in cents\', currency VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, idempotency_key VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_6D28840D5A18FBC7 (stripe_checkout_session_id), UNIQUE INDEX UNIQ_6D28840DFC72F97E (stripe_payment_intent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, brand_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, stock_keeping_unit VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, attributes JSON DEFAULT NULL, price_ht INT NOT NULL COMMENT \'Amount in cents\', vat_rate DOUBLE PRECISION NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX UNIQ_209AA41DA3F03A7E (stock_keeping_unit), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stripe_event (id INT AUTO_INCREMENT NOT NULL, stripe_event_id VARCHAR(180) NOT NULL, type VARCHAR(100) NOT NULL, payload JSON DEFAULT NULL, received_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, processing_status VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_110C630A2CB034B8 (stripe_event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE stripe_event');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
