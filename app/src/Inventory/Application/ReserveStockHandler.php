<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Repository\ReservationRepositoryInterface;
use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use App\Inventory\Domain\Reservation;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;
use Symfony\Component\Uid\Uuid;

final class ReserveStockHandler
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function reserveStock(Sku $sku, int $quantity): Reservation
    {
        return $this->transactionManager->run(function () use ($sku, $quantity): Reservation {
            $stock = $this->stockRepository->getBySkuForUpdate($sku);
            $stock->reserve($quantity);

            $reservation = new Reservation(Uuid::v7()->toRfc4122(), $stock, $quantity);
            $this->reservationRepository->add($reservation);
            $this->stockMovementRepository->add(StockMovement::reservationCreated($stock, $reservation));

            return $reservation;
        });
    }
}
