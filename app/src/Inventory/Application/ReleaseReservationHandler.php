<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Domain\Repository\ReservationRepositoryInterface;
use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\StockMovement;

final class ReleaseReservationHandler
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function releaseReservation(string $id): void
    {
        $this->transactionManager->run(function () use ($id): void {
            $reservation = $this->reservationRepository->getByIdWithStockForUpdate($id);

            if (!$reservation->release()) {
                return;
            }

            $stock = $reservation->getStock();
            $stock->releaseReservation($reservation->getQuantity());

            $this->stockMovementRepository->add(StockMovement::reservationReleased($stock, $reservation));
        });
    }
}
