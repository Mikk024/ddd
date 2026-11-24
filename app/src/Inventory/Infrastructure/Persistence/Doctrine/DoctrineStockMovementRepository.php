<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Repository\StockMovementRepositoryInterface;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\ValueObject\Sku;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineStockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function add(StockMovement $movement): void
    {
        $this->entityManager->persist($movement);
    }

    public function getBySku(Sku $sku): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('movement', 'reservation')
            ->from(StockMovement::class, 'movement')
            ->join('movement.stock', 'stock')
            ->leftJoin('movement.reservation', 'reservation')
            ->where('stock.sku.sku = :sku')
            ->setParameter('sku', $sku->getSku())
            ->orderBy('movement.occurredAt', 'DESC')
            ->addOrderBy('movement.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
