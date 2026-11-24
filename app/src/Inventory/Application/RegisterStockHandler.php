<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Exception\StockAlreadyExists;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\Stock;
use App\Inventory\Domain\ValueObject\Sku;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class RegisterStockHandler
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function register(Sku $sku): Stock
    {
        try {
            return $this->transactionManager->run(function () use ($sku): Stock {
                $stock = new Stock($sku);
                $this->stockRepository->add($stock);

                return $stock;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new StockAlreadyExists($sku->getSku(), previous: $exception);
        }
    }
}
