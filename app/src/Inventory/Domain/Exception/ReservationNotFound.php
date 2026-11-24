<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use RuntimeException;

final class ReservationNotFound extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Reservation "%s" was not found.', $id));
    }
}
