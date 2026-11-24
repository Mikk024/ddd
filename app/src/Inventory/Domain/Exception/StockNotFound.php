<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use RuntimeException;

final class StockNotFound extends RuntimeException
{
    public function __construct(string $sku)
    {
        parent::__construct(sprintf('Stock for SKU "%s" was not found.', $sku));
    }
}
