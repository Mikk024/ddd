<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use RuntimeException;
use Throwable;

final class StockAlreadyExists extends RuntimeException
{
    public function __construct(string $sku, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Stock for SKU "%s" already exists.', $sku), 0, $previous);
    }
}
