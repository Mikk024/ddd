<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Exception;

class InvalidReceiptQuantity extends Exception
{
    private const EXCEPTION_MESSAGE = 'Invalid Receipt Quantity';

    public function __construct()
    {
        parent::__construct(self::EXCEPTION_MESSAGE);
    }
}