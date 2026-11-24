<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Stock;
use App\Inventory\Domain\ValueObject\Sku;

interface StockRepositoryInterface
{
    public function add(Stock $stock): void;

    public function getBySku(Sku $sku): Stock;

    public function getBySkuForUpdate(Sku $sku): Stock;
}
