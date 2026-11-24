<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Application\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $operation());
    }
}
