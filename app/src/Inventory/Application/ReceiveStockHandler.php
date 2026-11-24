<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;

final class ReceiveStockHandler
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function receive(Sku $sku, int $quantity): void
    {
        $this->transactionManager->run(function () use ($sku, $quantity): void {
            $stock = $this->stockRepository->getBySkuForUpdate($sku);
            $stock->receive($quantity);
            $this->stockMovementRepository->add(StockMovement::receipt($stock, $quantity));
        });
    }
}
