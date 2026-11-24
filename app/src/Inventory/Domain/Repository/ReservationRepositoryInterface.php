<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Reservation;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function getByIdWithStockForUpdate(string $id): Reservation;
}
