<?php

declare(strict_types=1);

namespace App\Inventory\Application;

interface TransactionManagerInterface
{
    public function run(callable $operation): mixed;
}
