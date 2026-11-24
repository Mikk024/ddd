<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Exception;

class InvalidReleaseReservation extends Exception
{
    private const EXCEPTION_MESSAGE = 'Invalid Adjustement';

    public function __construct()
    {
        parent::__construct(self::EXCEPTION_MESSAGE);
    }
}