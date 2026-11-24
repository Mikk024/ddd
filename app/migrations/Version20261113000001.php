<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align inventory foreign-key indexes with Doctrine mapping names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_inventory_reservation_stock RENAME TO IDX_363A759ADCD6110');
        $this->addSql('ALTER INDEX idx_inventory_movement_stock RENAME TO IDX_1A8FA73DCD6110');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_1A8FA73DCD6110 RENAME TO idx_inventory_movement_stock');
        $this->addSql('ALTER INDEX IDX_363A759ADCD6110 RENAME TO idx_inventory_reservation_stock');
    }
}
