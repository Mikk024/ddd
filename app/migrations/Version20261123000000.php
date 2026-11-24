<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow signed inventory adjustment movements and constrain movement types.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_stock_movement DROP CONSTRAINT chk_inventory_movement_quantity');
        $this->addSql('ALTER TABLE inventory_stock_movement ADD CONSTRAINT chk_inventory_movement_quantity CHECK (quantity <> 0)');
        $this->addSql("ALTER TABLE inventory_stock_movement ADD CONSTRAINT chk_inventory_movement_type CHECK (type IN ('receipt', 'adjustment', 'reservation_created', 'reservation_released'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_stock_movement DROP CONSTRAINT chk_inventory_movement_type');
        $this->addSql('ALTER TABLE inventory_stock_movement DROP CONSTRAINT chk_inventory_movement_quantity');
        $this->addSql('ALTER TABLE inventory_stock_movement ADD CONSTRAINT chk_inventory_movement_quantity CHECK (quantity > 0)');
    }
}
