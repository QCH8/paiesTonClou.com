<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224142000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert project tables to InnoDB and add missing foreign keys';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $tables = [
            'user',
            'category',
            'product',
            'product_variant',
            'cart',
            'cart_item',
            'order',
            'order_item',
            'payment',
            'stripe_event',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE `%s` ENGINE = InnoDB', $table));
        }

        $this->addForeignKeyIfMissing(
            'FK_BA388B7A76ED395',
            'ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)'
        );
        $this->addForeignKeyIfMissing(
            'FK_F0FE25271AD5CDBF',
            'ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE'
        );
        $this->addForeignKeyIfMissing(
            'FK_F0FE2527A80EF684',
            'ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id)'
        );
        $this->addForeignKeyIfMissing(
            'FK_F5299398A76ED395',
            'ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL'
        );
        $this->addForeignKeyIfMissing(
            'FK_F52993981AD5CDBF',
            'ALTER TABLE `order` ADD CONSTRAINT FK_F52993981AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE SET NULL'
        );
        $this->addForeignKeyIfMissing(
            'FK_52EA1F098D9F6D38',
            'ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE'
        );
        $this->addForeignKeyIfMissing(
            'FK_52EA1F09A80EF684',
            'ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL'
        );
        $this->addForeignKeyIfMissing(
            'FK_6D28840D8D9F6D38',
            'ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE'
        );
        $this->addForeignKeyIfMissing(
            'FK_D34A04AD12469DE2',
            'ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)'
        );
        $this->addForeignKeyIfMissing(
            'FK_209AA41D4584665A',
            'ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id)'
        );
        $this->addForeignKeyIfMissing(
            'FK_110C630A8D9F6D38',
            'ALTER TABLE stripe_event ADD CONSTRAINT FK_110C630A8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE SET NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $constraints = [
            'FK_BA388B7A76ED395' => 'cart',
            'FK_F0FE25271AD5CDBF' => 'cart_item',
            'FK_F0FE2527A80EF684' => 'cart_item',
            'FK_F5299398A76ED395' => 'order',
            'FK_F52993981AD5CDBF' => 'order',
            'FK_52EA1F098D9F6D38' => 'order_item',
            'FK_52EA1F09A80EF684' => 'order_item',
            'FK_6D28840D8D9F6D38' => 'payment',
            'FK_D34A04AD12469DE2' => 'product',
            'FK_209AA41D4584665A' => 'product_variant',
            'FK_110C630A8D9F6D38' => 'stripe_event',
        ];

        foreach ($constraints as $name => $table) {
            if ($this->foreignKeyExists($name)) {
                $this->addSql(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY %s', $table, $name));
            }
        }
    }

    private function addForeignKeyIfMissing(string $constraintName, string $sql): void
    {
        if (!$this->foreignKeyExists($constraintName)) {
            $this->addSql($sql);
        }
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        $query = <<<'SQL'
SELECT COUNT(*)
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  AND CONSTRAINT_NAME = :name
SQL;

        return (int) $this->connection->fetchOne($query, ['name' => $constraintName]) > 0;
    }
}

