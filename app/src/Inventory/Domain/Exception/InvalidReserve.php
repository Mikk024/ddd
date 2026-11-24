<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Exception;

class InvalidReserve extends Exception
{
    private const EXCEPTION_MESSAGE = 'Reservation quantity must be positive and not exceed available stock.';

    public function __construct()
    {
        parent::__construct(self::EXCEPTION_MESSAGE);
    }
}
