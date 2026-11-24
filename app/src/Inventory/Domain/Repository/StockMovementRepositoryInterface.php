<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;

interface StockMovementRepositoryInterface
{
    public function add(StockMovement $movement): void;

    /** @return list<StockMovement> */
    public function getBySku(Sku $sku): array;
}
