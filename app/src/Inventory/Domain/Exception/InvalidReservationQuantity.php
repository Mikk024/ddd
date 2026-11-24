<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Exception;

class InvalidReservationQuantity extends Exception
{
    private const EXCEPTION_MESSAGE = 'Invalid Reservation Quantity';

    public function __construct()
    {
        parent::__construct(self::EXCEPTION_MESSAGE);
    }
}