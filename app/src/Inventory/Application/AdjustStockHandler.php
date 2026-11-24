<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;

final class AdjustStockHandler
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    public function adjust(Sku $sku, int $delta, string $reason): void
    {
        $this->transactionManager->run(function () use ($sku, $delta, $reason): void {
            $stock = $this->stockRepository->getBySkuForUpdate($sku);
            $stock->adjust($delta, $reason);
            $this->stockMovementRepository->add(StockMovement::adjustment($stock, $delta, $reason));
        });
    }
}
