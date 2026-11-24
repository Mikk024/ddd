<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use Exception;

class InvalidSku extends Exception
{
    private const VALIDATION_MESSAGE = 'Invalid Sku';

    public function __construct()
    {
        parent::__construct(self::VALIDATION_MESSAGE);
    }
}